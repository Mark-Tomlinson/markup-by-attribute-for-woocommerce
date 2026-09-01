<?php
/**
 * Item 23 — detect markups that are advertised but can never be charged
 *
 * An attribute left as "Any" on a variation makes WooCommerce widen that
 * attribute's drop-down to every option the product selected -- measured in
 * WooCommerce 11.0.1, class-wc-product-variable-data-store-cpt.php:273-275:
 *
 *     // Empty value indicates that all options for given attribute are available.
 *     if ( in_array( null, $values, true ) || in_array( '', $values, true ) || empty( $values ) ) {
 *         $values = $attribute['is_taxonomy'] ? wc_get_object_terms( ... ) : ...;
 *
 * Frontend\Options annotates every option in that widened list, but the cart
 * resolves to the "Any" variation, whose price carries no markup for the option
 * the customer picked. So the markup is advertised and never charged -- and when
 * it is negative (the wp.org thread's -£600 "No cushions") the customer is
 * OVERCHARGED against what the drop-down promised.
 *
 * The whole difficulty is false positives. A store that follows the advised
 * workflow correctly leaves plenty of attributes on "Any" quite legitimately;
 * mallen1255's own product has three of them. The markup test is the only guard,
 * and cases 3, 4 and 6 below are that guard.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/backend/handlers/bulkmetaio.php';
require __DIR__ . '/../src/backend/product.php';

use mt2Tech\MarkupByAttribute\Backend\Product;

const P = 99;
const V = [201, 202];

/**
 * Reset the world. $markups is [term_id => advertised amount] as
 * bulkSaveProductMarkupValues() would have written it at the last reprice;
 * $rows is what each variation holds for its attributes.
 */
function t28_fixture(array $markups, array $rows): void {
	$GLOBALS['wpdb'] = new MT2MBA_Fake_WPDB();
	$GLOBALS['mt2mba_stub']['meta'] = [];
	$GLOBALS['mt2mba_stub']['term_meta_in'] = [];

	foreach ($markups as $term_id => $amount) {
		$GLOBALS['mt2mba_stub']['meta']["mt2mba_{$term_id}_markup_amount"] = $amount;
	}

	t_product(P,
		[
			'pa_size'  => [t_term(31, 'xx-large', 'XX-Large'), t_term(32, 'large', 'Large')],
			'pa_color' => [t_term(41, 'red', 'Red'), t_term(42, 'blue', 'Blue')],
		],
		['pa_size' => 'Size', 'pa_color' => 'Colour']
	);

	$GLOBALS['mt2mba_stub']['wpdb_results'] = t_wpdb_map(["LIKE 'attribute_pa_" => $rows]);
}

/** Run the detector against the product the fixture registered. */
function t28_detect(): string {
	$labels = Product::findUnchargeableAttributes(wc_get_product(P), P, V);
	return empty($labels) ? '(none)' : implode('; ', $labels);
}

/** Assert on the flattened result, showing both sides when it fails. */
function t28_expect(string $expected, string $description): void {
	$actual = t28_detect();
	t_assert($actual === $expected,
		$actual === $expected
			? $description
			: "$description\n        expected: $expected\n        actual:   $actual");
}

//region 1. A markup on a term, and some variation is "Any" on that attribute
// The reported bug. XX-Large advertises +1.64 in the drop-down; variation 202
// is "Any Size", so nothing charges it.
t28_fixture(
	[31 => '1.64'],
	[
		t_meta_row(201, 'attribute_pa_size',  'large'),
		t_meta_row(201, 'attribute_pa_color', 'red'),
		t_meta_row(202, 'attribute_pa_size',  ''),		// "Any Size"
		t_meta_row(202, 'attribute_pa_color', 'blue'),
	]
);
t28_expect('Size', 'a markup on an attribute left "Any" is flagged, by attribute name');
//endregion

//region 2. A markup, but every variation names a specific term
// Nothing is over-advertised: WooCommerce narrows the drop-down to the slugs the
// variations actually use, so an unreachable term is never even offered.
t28_fixture(
	[31 => '1.64'],
	[
		t_meta_row(201, 'attribute_pa_size',  'xx-large'),
		t_meta_row(201, 'attribute_pa_color', 'red'),
		t_meta_row(202, 'attribute_pa_size',  'large'),
		t_meta_row(202, 'attribute_pa_color', 'blue'),
	]
);
t28_expect('(none)', 'a fully specified product is silent');
//endregion

//region 3. "Any" on an attribute that carries no markup — the mallen1255 shape
// THE false positive that matters. His product holds eight variations and three
// attributes set to "Any", and every one of them is correct. A naive "any Any"
// detector fires on every store that follows the advice properly.
t28_fixture(
	[31 => '1.64'],								// markup is on Size...
	[
		t_meta_row(201, 'attribute_pa_size',  'xx-large'),
		t_meta_row(201, 'attribute_pa_color', ''),	// ...but Colour is the "Any"
		t_meta_row(202, 'attribute_pa_size',  'large'),
		t_meta_row(202, 'attribute_pa_color', ''),
	]
);
t28_expect('(none)', 'an "Any" on a markup-free attribute is not flagged');
//endregion

//region 4. A markup on an attribute whose "Used for variations" is OFF
// Frontend\Options hooks woocommerce_dropdown_variation_attribute_options_html,
// which never fires for a non-variation attribute -- no drop-down, nothing
// advertised. WooCommerce agrees: read_variation_attributes() skips them outright
// (`if ( empty( $attribute['is_variation'] ) ) continue;`).
//
// This is also the transient state the documented workflow REQUIRES: uncheck
// all but the markup-bearing attributes, generate, set prices, re-check all. A
// detector without this guard fires in the middle of the procedure that fixes the
// problem. Written to fail first -- it passes against a detector that omits the
// get_variation() filter, so green-from-birth would prove nothing.
t28_fixture(
	[31 => '1.64'],
	[
		t_meta_row(201, 'attribute_pa_size',  ''),
		t_meta_row(201, 'attribute_pa_color', 'red'),
		t_meta_row(202, 'attribute_pa_size',  ''),
		t_meta_row(202, 'attribute_pa_color', 'blue'),
	]
);
$GLOBALS['mt2mba_stub']['products'][P] = new MT2MBA_Fake_Product([
	'pa_size'  => new MT2MBA_Fake_Attribute('pa_size',  true, [31, 32], false),
	'pa_color' => new MT2MBA_Fake_Attribute('pa_color', true, [41, 42], true),
]);
t28_expect('(none)', 'an attribute with "Used for variations" off is never flagged');
//endregion

//region 5. The attribute row was never written at all
// WooCommerce writes attribute_pa_x = '' for an explicit "Any", but when an
// attribute gains "Used for variations" AFTER the variations already exist, the
// row may not be written. Missing and empty both mean Any, which is why the test
// is empty() and not === '' -- a meta_value = '' SQL predicate would miss this.
t28_fixture(
	[31 => '1.64'],
	[
		t_meta_row(201, 'attribute_pa_size',  'xx-large'),
		t_meta_row(201, 'attribute_pa_color', 'red'),
		t_meta_row(202, 'attribute_pa_color', 'blue'),	// no pa_size row at all
	]
);
t28_expect('Size', 'a missing attribute row counts as "Any"');
//endregion

//region 6. A product that has never been repriced advertises nothing
// The drop-down annotation reads mt2mba_{term_id}_markup_amount (Frontend\Options
// line 159) and skips the term when it is absent. Reading the same meta means the
// detector cannot warn about a markup no customer is being shown.
t28_fixture(
	[],											// no reprice has happened yet
	[
		t_meta_row(201, 'attribute_pa_size',  ''),
		t_meta_row(201, 'attribute_pa_color', 'red'),
		t_meta_row(202, 'attribute_pa_size',  ''),
		t_meta_row(202, 'attribute_pa_color', 'blue'),
	]
);
t28_expect('(none)', 'a product with no markup amounts stored is silent');
//endregion

//region 7. Two bad attributes — both are listed
// The notice states the rule once and bullets the instances: naming only one
// would read as though it were the only attribute that could ever be affected.
// Collecting them all is free -- the variation rows are one bulk read. Colour also
// carries a NEGATIVE markup here, the -600 shape from the wp.org thread, where the
// customer is overcharged against what the drop-down advertised.
t28_fixture(
	[31 => '1.64', 41 => '-600.00'],
	[
		t_meta_row(201, 'attribute_pa_size',  ''),
		t_meta_row(201, 'attribute_pa_color', ''),
		t_meta_row(202, 'attribute_pa_size',  'large'),
		t_meta_row(202, 'attribute_pa_color', 'blue'),
	]
);
t28_expect('Size; Colour', 'every offending attribute is listed, in product attribute order');
//endregion

t_done();
