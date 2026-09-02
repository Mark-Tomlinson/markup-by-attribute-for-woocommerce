<?php
/**
 * Item 15 — markup meta must only be written for terms the product selected
 *
 * getAttributeData() asked get_terms() for every term in each of the product's
 * taxonomies (hide_empty => false) and never consulted the product's own
 * selections, so mt2mba_{term_id}_markup_amount rows were written for terms the
 * product does not offer. Reproduced: a product across three attributes holding
 * 11 markup-bearing terms wrote 11 rows; deselecting six of them and repricing
 * still wrote 11.
 *
 * Harmless to read -- the storefront only ever looks up the terms it displays --
 * but it is DB writes and postmeta bloat proportional to taxonomy size, not to
 * the product. A 300-term Size attribute costs every product ~300 rows a reprice.
 *
 * The rows are rewritten wholesale on every reprice (bulkSaveProductMarkupValues
 * deletes them all first), so the fix is self-healing: existing bloat clears on
 * the next reprice with no upgrade routine.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/utility/general.php';
require __DIR__ . '/../src/backend/handlers/bulkmetaio.php';
require __DIR__ . '/../src/backend/handlers/pricemarkuphandler.php';
require __DIR__ . '/../src/backend/handlers/pricesethandler.php';

use mt2Tech\MarkupByAttribute\Backend\Handlers\PriceSetHandler;

const P = 99;
const V = [201, 202];

/**
 * pa_color holds five terms, four of them with markups. Which of them the
 * product selected is the variable under test.
 */
function t21_fixture(?array $selected = null): void {
	$GLOBALS['wpdb'] = new MT2MBA_Fake_WPDB();
	$GLOBALS['mt2mba_stub']['term_meta_in'] = [];
	$GLOBALS['mt2mba_stub']['meta'] = [];

	$terms = [
		t_term(11, 'red',   'Red',   '5'),
		t_term(12, 'blue',  'Blue',  '10%'),
		t_term(13, 'green', 'Green'),		// no markup: never gets a row either way
		t_term(14, 'teal',  'Teal',  '7'),
		t_term(15, 'pink',  'Pink',  '3%'),
	];

	t_product(P, ['pa_color' => $terms], ['pa_color' => 'Color'],
		$selected === null ? [] : ['pa_color' => $selected]);

	$GLOBALS['mt2mba_stub']['wpdb_results'] = t_wpdb_map([
		"LIKE 'attribute_pa_" => [
			t_meta_row(201, 'attribute_pa_color', 'red'),
			t_meta_row(202, 'attribute_pa_color', 'blue'),
		],
		"meta_key = '_variation_description'" => [
			t_meta_row(201, '_variation_description', ''),
			t_meta_row(202, '_variation_description', ''),
		],
	]);
}

/** The markup-amount INSERT, which is the statement under test. */
function t21_markup_insert(): string {
	foreach ($GLOBALS['wpdb']->queries as $query) {
		if (strpos($query, '_markup_amount') !== false && strpos($query, 'INSERT') === 0) {
			return preg_replace('/\s+/', ' ', trim($query));
		}
	}
	return '(no markup-amount INSERT)';
}

//region The product selects three of the taxonomy's five terms
t21_fixture([11, 12, 13]);
(new PriceSetHandler('variable_regular_price', ['value' => '100'], P, V))->processProductMarkups();

$expected = "INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES "
	. "(99, 'mt2mba_11_markup_amount', '5.00'), (99, 'mt2mba_12_markup_amount', '10.00')";
$actual = t21_markup_insert();
t_assert($actual === $expected,
	$actual === $expected
		? 'only the selected terms get markup rows (Teal and Pink excluded)'
		: "only the selected terms get markup rows\n        expected: $expected\n        actual:   $actual");
//endregion

//region Every term selected — the ordinary case must be untouched
t21_fixture();		// defaults to all five
(new PriceSetHandler('variable_regular_price', ['value' => '100'], P, V))->processProductMarkups();

$expected_all = "INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES "
	. "(99, 'mt2mba_11_markup_amount', '5.00'), (99, 'mt2mba_12_markup_amount', '10.00'), "
	. "(99, 'mt2mba_14_markup_amount', '7.00'), (99, 'mt2mba_15_markup_amount', '3.00')";
$actual_all = t21_markup_insert();
t_assert($actual_all === $expected_all,
	$actual_all === $expected_all
		? 'a product selecting every term still gets every markup row'
		: "a product selecting every term still gets every markup row\n        expected: $expected_all\n        actual:   $actual_all");
//endregion

//region Nothing selected — the empty-include trap
// get_terms() reads an empty 'include' as NO restriction, so a fix that passes
// the selections straight through silently writes the whole taxonomy again.
// WooCommerce's own UI may not be able to produce this state, but a CSV import,
// the REST API or another plugin can.
t21_fixture([]);
(new PriceSetHandler('variable_regular_price', ['value' => '100'], P, V))->processProductMarkups();

$actual_none = t21_markup_insert();
t_assert($actual_none === '(no markup-amount INSERT)',
	$actual_none === '(no markup-amount INSERT)'
		? 'an attribute with nothing selected writes no markup rows'
		: "an attribute with nothing selected writes no markup rows\n        actual: $actual_none");
//endregion

//region Attributes not used for variations still carry their markups
// The documented workflow for large variation counts: clear "Used for variations"
// on the markup-free attributes, generate and price, then re-select. At price
// time the markup-bearing attribute may itself be flagged not-for-variations, so
// filtering on get_variation() here would strip exactly the rows that workflow
// depends on. This guards against that filter being added later.
t21_fixture([11, 12]);
$GLOBALS['mt2mba_stub']['products'][P] = new MT2MBA_Fake_Product([
	'pa_color' => new MT2MBA_Fake_Attribute('pa_color', true, [11, 12], false),
]);
(new PriceSetHandler('variable_regular_price', ['value' => '100'], P, V))->processProductMarkups();

$expected_nonvar = "INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES "
	. "(99, 'mt2mba_11_markup_amount', '5.00'), (99, 'mt2mba_12_markup_amount', '10.00')";
$actual_nonvar = t21_markup_insert();
t_assert($actual_nonvar === $expected_nonvar,
	$actual_nonvar === $expected_nonvar
		? 'an attribute not used for variations still gets its markup rows'
		: "an attribute not used for variations still gets its markup rows\n        expected: $expected_nonvar\n        actual:   $actual_nonvar");
//endregion

t_done();
