<?php
/**
 * Items 7 & 8 — characterization test: the exact SQL every handler path emits.
 *
 * Items 7 and 8 are behavior-preserving refactors, so there is no bug to
 * reproduce red-then-green. This file is the substitute: it pins the current
 * statements, in order, for every path through the three handlers. It was
 * written and passing against f2ae310 BEFORE either refactor started, so any
 * later diff in these strings is a regression, not an improvement.
 *
 * Deliberately snapshots whole statements rather than shapes — the whole point
 * is that a changed placeholder count, a dropped WHERE clause, or a reordered
 * DELETE/INSERT pair shows up as a failure.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/utility/general.php';
require __DIR__ . '/../src/backend/handlers/bulkmetaio.php';
require __DIR__ . '/../src/backend/handlers/pricemarkuphandler.php';
require __DIR__ . '/../src/backend/handlers/pricesethandler.php';
require __DIR__ . '/../src/backend/handlers/priceupdatehandler.php';
require __DIR__ . '/../src/backend/handlers/markupdeletehandler.php';

use mt2Tech\MarkupByAttribute\Backend\Handlers\PriceSetHandler;
use mt2Tech\MarkupByAttribute\Backend\Handlers\PriceUpdateHandler;
use mt2Tech\MarkupByAttribute\Backend\Handlers\MarkupDeleteHandler;

//region Fixture — one product, one attribute, three variations
// pa_color: Red (+5 fixed), Blue (+10%), Green (no markup, so no meta row and
// no description line — it exercises the "term the table skips" branch).
const P  = 99;                    // product
const V  = [201, 202, 203];       // variations: red, blue, green

function fixtures(): void {
	$GLOBALS['wpdb'] = new MT2MBA_Fake_WPDB();
	$GLOBALS['mt2mba_stub']['term_meta_in'] = [];
	$GLOBALS['mt2mba_stub']['meta'] = [];
	// Recorded activity accumulates across scenarios otherwise
	$GLOBALS['mt2mba_test']['post_meta']  = [];
	$GLOBALS['mt2mba_test']['meta_reads'] = [];
	$GLOBALS['mt2mba_test']['transients'] = [];

	t_product(P, ['pa_color' => [
		t_term(11, 'red',   'Red',   '5'),
		t_term(12, 'blue',  'Blue',  '10%'),
		t_term(13, 'green', 'Green'),
	]], ['pa_color' => 'Color']);

	// Three description shapes: none, markup-in-the-middle, markup-only. The
	// last two drive both branches of removeVariationPrices().
	$GLOBALS['mt2mba_stub']['wpdb_results'] = t_wpdb_map([
		"LIKE 'attribute_pa_" => [
			t_meta_row(201, 'attribute_pa_color', 'red'),
			t_meta_row(202, 'attribute_pa_color', 'blue'),
			t_meta_row(203, 'attribute_pa_color', 'green'),
		],
		"meta_key = '_variation_description'" => [
			t_meta_row(201, '_variation_description', 'Hand dyed.'),
			t_meta_row(202, '_variation_description', 'Hand dyed.<span id="mbainfo">old</span>'),
			t_meta_row(203, '_variation_description', '<span id="mbainfo">only markup</span>'),
		],
	]);
}
//endregion

//region SQL assertion
/**
 * Compare the statements issued since the last fixtures() call against the
 * snapshot, whitespace-normalized. Reports the first difference rather than
 * just "not equal" so a failure is actionable.
 */
function t_assert_sql(string $label, array $expected): void {
	$actual = array_map(
		fn($q) => preg_replace('/\s+/', ' ', trim($q)),
		$GLOBALS['wpdb']->queries
	);

	if ($actual === $expected) {
		t_assert(true, "$label — " . count($actual) . ' statements match');
		return;
	}

	t_assert(false, "$label — SQL differs from the pinned snapshot");
	$max = max(count($actual), count($expected));
	for ($i = 0; $i < $max; $i++) {
		$a = $actual[$i]   ?? '(nothing)';
		$e = $expected[$i] ?? '(nothing)';
		if ($a !== $e) {
			echo "        first diff at statement $i\n";
			echo "          expected: $e\n";
			echo "          actual:   $a\n";
			return;
		}
	}
}
//endregion

//region 1. Set regular price — the full path
fixtures();
(new PriceSetHandler('variable_regular_price', ['value' => '100'], P, V))
	->processProductMarkups();

t_assert_sql('set regular price', [
	// Markup amounts are rewritten wholesale (regular price only)
	"DELETE FROM wp_postmeta WHERE post_id = 99 AND meta_key LIKE 'mt2mba\\\\_%\\\\_markup\\\\_amount'",
	"INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (99, 'mt2mba_11_markup_amount', '5.00'), (99, 'mt2mba_12_markup_amount', '10.00')",
	// Bulk read of what the variations are and say
	"SELECT post_id, meta_key, meta_value FROM wp_postmeta WHERE post_id IN (201,202,203) AND meta_key LIKE 'attribute_pa_%'",
	"SELECT post_id, meta_value FROM wp_postmeta WHERE post_id IN (201,202,203) AND meta_key = '_variation_description'",
	// Prices and descriptions, inside one transaction
	'START TRANSACTION',
	"DELETE FROM wp_postmeta WHERE post_id IN (201,202,203) AND meta_key IN ('_price', '_regular_price')",
	"INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (201, '_price', '105.00'), (201, '_regular_price', '105.00'), (202, '_price', '110.00'), (202, '_regular_price', '110.00'), (203, '_price', '100.00'), (203, '_regular_price', '100.00')",
	"DELETE FROM wp_postmeta WHERE post_id IN (201,202,203) AND meta_key = '_variation_description'",
	"INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (201, '_variation_description', 'Hand dyed. <span id=\\\"mbainfo\\\">Product price \$100.00 Add \$5.00 for Red </span>'), (202, '_variation_description', 'Hand dyed. <span id=\\\"mbainfo\\\">Product price \$100.00 Add \$10.00 for Blue </span>'), (203, '_variation_description', '')",
	'COMMIT',
]);
//endregion

//region 2. Set sale price — no markup-amount rewrite, descriptions preserved verbatim
fixtures();
$GLOBALS['mt2mba_stub']['meta']['mt2mba_base_regular_price'] = '100';
(new PriceSetHandler('variable_sale_price', ['value' => '80'], P, V))
	->processProductMarkups();

t_assert_sql('set sale price', [
	"SELECT post_id, meta_key, meta_value FROM wp_postmeta WHERE post_id IN (201,202,203) AND meta_key LIKE 'attribute_pa_%'",
	"SELECT post_id, meta_value FROM wp_postmeta WHERE post_id IN (201,202,203) AND meta_key = '_variation_description'",
	'START TRANSACTION',
	"DELETE FROM wp_postmeta WHERE post_id IN (201,202,203) AND meta_key IN ('_price', '_sale_price')",
	"INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (201, '_price', '85.00'), (201, '_sale_price', '85.00'), (202, '_price', '88.00'), (202, '_sale_price', '88.00'), (203, '_price', '80.00'), (203, '_sale_price', '80.00')",
	"DELETE FROM wp_postmeta WHERE post_id IN (201,202,203) AND meta_key = '_variation_description'",
	"INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (201, '_variation_description', 'Hand dyed.'), (202, '_variation_description', 'Hand dyed.<span id=\\\"mbainfo\\\">old</span>'), (203, '_variation_description', '<span id=\\\"mbainfo\\\">only markup</span>')",
	'COMMIT',
]);
//endregion

//region 3. Blank out the regular price — the one path where the clear-list and write-list differ
// 201 has no markup span, so it is skipped entirely; only 202 and 203 are
// cleared, while the SELECT covered all three. Item 8's replaceMeta() must keep
// those two lists independent or this snapshot changes.
fixtures();
(new PriceSetHandler('variable_regular_price', ['value' => ''], P, V))
	->processProductMarkups();

t_assert_sql('blank out regular price', [
	"SELECT post_id, meta_value FROM wp_postmeta WHERE post_id IN (201,202,203) AND meta_key = '_variation_description'",
	"DELETE FROM wp_postmeta WHERE post_id IN (202,203) AND meta_key = '_variation_description'",
	"INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (202, '_variation_description', 'Hand dyed.'), (203, '_variation_description', '')",
]);
//endregion

//region 4. Increase regular price 10% — routes through PriceUpdateHandler into a nested PriceSetHandler
// Base 100 + 10% = 110, and the percentage markup recalculates against it (11.00, not 10.00).
fixtures();
$GLOBALS['mt2mba_stub']['meta']['mt2mba_base_regular_price'] = '100';
(new PriceUpdateHandler('variable_regular_price_increase', ['value' => '10%'], P, V))
	->processProductMarkups();

t_assert_sql('increase regular price 10%', [
	"DELETE FROM wp_postmeta WHERE post_id = 99 AND meta_key LIKE 'mt2mba\\\\_%\\\\_markup\\\\_amount'",
	"INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (99, 'mt2mba_11_markup_amount', '5.00'), (99, 'mt2mba_12_markup_amount', '11.00')",
	"SELECT post_id, meta_key, meta_value FROM wp_postmeta WHERE post_id IN (201,202,203) AND meta_key LIKE 'attribute_pa_%'",
	"SELECT post_id, meta_value FROM wp_postmeta WHERE post_id IN (201,202,203) AND meta_key = '_variation_description'",
	'START TRANSACTION',
	"DELETE FROM wp_postmeta WHERE post_id IN (201,202,203) AND meta_key IN ('_price', '_regular_price')",
	"INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (201, '_price', '115.00'), (201, '_regular_price', '115.00'), (202, '_price', '121.00'), (202, '_regular_price', '121.00'), (203, '_price', '110.00'), (203, '_regular_price', '110.00')",
	"DELETE FROM wp_postmeta WHERE post_id IN (201,202,203) AND meta_key = '_variation_description'",
	"INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (201, '_variation_description', 'Hand dyed. <span id=\\\"mbainfo\\\">Product price \$110.00 Add \$5.00 for Red </span>'), (202, '_variation_description', 'Hand dyed. <span id=\\\"mbainfo\\\">Product price \$110.00 Add \$11.00 for Blue </span>'), (203, '_variation_description', '')",
	'COMMIT',
]);
//endregion

//region 5. Decrease regular price by 5 fixed — 100 - 5 = 95
fixtures();
$GLOBALS['mt2mba_stub']['meta']['mt2mba_base_regular_price'] = '100';
(new PriceUpdateHandler('variable_regular_price_decrease', ['value' => '5'], P, V))
	->processProductMarkups();

t_assert_sql('decrease regular price by 5', [
	"DELETE FROM wp_postmeta WHERE post_id = 99 AND meta_key LIKE 'mt2mba\\\\_%\\\\_markup\\\\_amount'",
	"INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (99, 'mt2mba_11_markup_amount', '5.00'), (99, 'mt2mba_12_markup_amount', '9.50')",
	"SELECT post_id, meta_key, meta_value FROM wp_postmeta WHERE post_id IN (201,202,203) AND meta_key LIKE 'attribute_pa_%'",
	"SELECT post_id, meta_value FROM wp_postmeta WHERE post_id IN (201,202,203) AND meta_key = '_variation_description'",
	'START TRANSACTION',
	"DELETE FROM wp_postmeta WHERE post_id IN (201,202,203) AND meta_key IN ('_price', '_regular_price')",
	"INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (201, '_price', '100.00'), (201, '_regular_price', '100.00'), (202, '_price', '104.50'), (202, '_regular_price', '104.50'), (203, '_price', '95.00'), (203, '_regular_price', '95.00')",
	"DELETE FROM wp_postmeta WHERE post_id IN (201,202,203) AND meta_key = '_variation_description'",
	"INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (201, '_variation_description', 'Hand dyed. <span id=\\\"mbainfo\\\">Product price \$95.00 Add \$5.00 for Red </span>'), (202, '_variation_description', 'Hand dyed. <span id=\\\"mbainfo\\\">Product price \$95.00 Add \$9.50 for Blue </span>'), (203, '_variation_description', '')",
	'COMMIT',
]);
//endregion

//region 6. Delete all variations — one sweeping meta delete
fixtures();
(new MarkupDeleteHandler(P))->processProductMarkups();

t_assert_sql('delete all markup meta', [
	"DELETE FROM wp_postmeta WHERE post_id = 99 AND meta_key LIKE 'mt2mba\\\\_%'",
]);
//endregion

//region 7. Item 2's guard — a variation-less product issues no SQL at all
fixtures();
(new PriceSetHandler('variable_regular_price', ['value' => ''], P, []))
	->processProductMarkups();

t_assert_sql('blank out regular price, no variations', []);
//endregion

//region The base-price meta the SQL snapshot cannot see
// handleBasePriceUpdate() and removeVariationPrices() go through the WordPress
// meta API rather than $wpdb, so they leave no statement to pin above.
fixtures();
(new PriceSetHandler('variable_regular_price', ['value' => '100'], P, V))
	->processProductMarkups();

$writes = array_values(array_filter(
	$GLOBALS['mt2mba_test']['post_meta'],
	fn($m) => $m[0] === 'update' && $m[2] === 'mt2mba_base_regular_price'
));
t_assert(
	count($writes) === 1 && $writes[0][1] === P && (float) $writes[0][3] === 100.0,
	'base regular price meta written once, as 100'
);
t_assert(
	($GLOBALS['mt2mba_test']['transients']['mt2mba_current_base_' . P] ?? null) == 100,
	'current-base transient set for the regular-price pass'
);
//endregion

t_done();
