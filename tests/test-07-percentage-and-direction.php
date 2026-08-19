<?php
/**
 * Item 9 — name the strpos() truthiness idioms.
 *
 * Two questions were being asked with the truthiness of strpos(), which is
 * only correct by accident of data shape (a match at position 0 reads false):
 *
 *   1. "is this markup a percentage?"  — four sites, while validateMarkupValue()
 *      already answered it correctly with substr($markup, -1) === '%'. That test
 *      is now General::isPercentage() and all five sites share it.
 *   2. "is this bulk action a decrease / a price change?" — now explicit !== false.
 *
 * Behavior is unchanged for every value the plugin actually produces, but
 * isPercentage() is NOT a byte-identical swap for strpos(): "5%0" was truthy
 * before and is false now. Nothing survives validateMarkupValue() in that shape.
 *
 * KNOWN i18n GAPS (pre-existing — the old strpos() truthiness failed identically,
 * so item 9 neither introduced nor fixed them):
 *   - A leading percent sign ("%50", the Turkish convention) reads as a fixed
 *     amount. strpos() returned 0 there, which is falsy; substr(-1) is not '%'.
 *   - Non-ASCII percent signs are not recognized: U+066A Arabic (٪) and
 *     U+FF05 fullwidth (％). Both old and new see them as fixed amounts.
 * Fixing either means teaching validateMarkupValue() to normalize first —
 * floatval("%10") is 0, so detection alone would produce silently wrong math.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/utility/general.php';
require __DIR__ . '/../src/backend/handlers/bulkmetaio.php';
require __DIR__ . '/../src/backend/handlers/pricemarkuphandler.php';
require __DIR__ . '/../src/backend/handlers/pricesethandler.php';
require __DIR__ . '/../src/backend/handlers/priceupdatehandler.php';
require __DIR__ . '/../src/backend/handlers/markupdeletehandler.php';
require __DIR__ . '/../src/backend/product.php';

use mt2Tech\MarkupByAttribute\Utility\General;
use mt2Tech\MarkupByAttribute\Backend\Handlers\PriceUpdateHandler;
use mt2Tech\MarkupByAttribute\Backend\Product;

//region isPercentage — the shapes markup values actually take
$percentages = ['10%', '-5%', '0.5%', '100%', '-2.5%'];
foreach ($percentages as $value) {
	t_assert(General::isPercentage($value) === true, "isPercentage('$value') is true");
}

$amounts = ['5', '-5', '5.00', '0.5', '', '0'];
foreach ($amounts as $value) {
	t_assert(General::isPercentage($value) === false, "isPercentage('" . $value . "') is false");
}

// Whitespace tolerance. WooCommerce accepts a space before the percent sign in
// its bulk-edit prompt, and that value reaches calculateNewBasePrice() raw — it
// never passes through validateMarkupValue(). The space is interior, so the
// last character is still '%'; trim() covers the padded-string case.
foreach (['10 %', '5 %', '-17 %', '10  %', ' 10% ', '10% '] as $spaced) {
	t_assert(General::isPercentage($spaced) === true, "isPercentage('$spaced') is true (WooCommerce allows the space)");
}

// A leading percent sign is not recognized — matching the strpos() behavior it
// replaced, which read position 0 as false. Turkish-style "%50" is a known gap;
// see the i18n note in the header.
t_assert(General::isPercentage('%10') === false, "isPercentage('%10') is false (leading %, same as the old strpos)");

// The old strpos() truthiness and the new helper must agree on every shape the
// plugin can encounter — this is the guarantee that item 9 changed nothing
$shapes = ['5%', '5 %', '-17%', '-17 %', '10  %', '%10', '% 10', '10,5%', ' 10% ', '5', '-5', '5.00', '', '0'];
foreach ($shapes as $shape) {
	$old = (bool) strpos($shape, '%');
	t_assert(General::isPercentage($shape) === $old,
		"isPercentage('$shape') matches the old strpos() truthiness (" . ($old ? 'true' : 'false') . ')');
}
//endregion

//region isPercentage agrees with validateMarkupValue
// One definition of the split — a value validating with a % must read as a
// percentage, and vice versa
foreach (['10%', '-5%', '5', '-2.5', '0.5%'] as $input) {
	$validated = General::validateMarkupValue($input);
	t_assert(General::isPercentage($input) === General::isPercentage((string) $validated),
		"isPercentage() survives validation of '$input' (validated to '$validated')");
}
//endregion

//region Bulk-action direction — decrease negates, increase does not
$handler = new PriceUpdateHandler('variable_regular_price_increase', ['value' => '10'], 99, []);
$calc = new ReflectionMethod($handler, 'calculateNewBasePrice');
$calc->setAccessible(true);

$cases = [
	// [bulk action, markup, base price, expected new base price]
	['variable_regular_price_increase', '10',  100.0, 110.0],
	['variable_regular_price_decrease', '10',  100.0,  90.0],
	['variable_sale_price_increase',    '10',  100.0, 110.0],
	['variable_sale_price_decrease',    '10',  100.0,  90.0],
	['variable_regular_price_increase', '10%', 100.0, 110.0],
	['variable_regular_price_decrease', '10%', 100.0,  90.0],
	['variable_sale_price_decrease',    '25%', 200.0, 150.0],
	['variable_regular_price_increase', '5.5', 100.0, 105.5],
	// Locale notation on the bulk path — floatval() alone read these as 0 and 1
	['variable_regular_price_increase', '2 %',  100.0, 102.0],
	['variable_regular_price_increase', '%50',  100.0, 150.0],
	['variable_regular_price_decrease', '%50',  100.0,  50.0],
];
foreach ($cases as [$action, $markup, $base, $expected]) {
	$actual = $calc->invoke($handler, $action, $markup, $base);
	t_assert(abs($actual - $expected) < 0.00001,
		"$action by $markup on $base gives $expected (got $actual)");
}
//endregion

//region Dispatch — the increase/decrease branch still routes to PriceUpdateHandler
// PriceUpdateHandler's first act is reading mt2mba_base_<price_type>; with no
// base price stored it stops there, which makes the read a clean route marker.
$product = new Product();

$routes = [
	'variable_regular_price_increase' => 'mt2mba_base_regular_price',
	'variable_regular_price_decrease' => 'mt2mba_base_regular_price',
	'variable_sale_price_increase'    => 'mt2mba_base_sale_price',
	'variable_sale_price_decrease'    => 'mt2mba_base_sale_price',
];
foreach ($routes as $action => $expected_key) {
	$GLOBALS['mt2mba_test']['meta_reads'] = [];
	$product->handleBulkPriceAction($action, ['value' => '10'], 99, []);
	$keys = array_column($GLOBALS['mt2mba_test']['meta_reads'], 2);
	t_assert(in_array($expected_key, $keys, true),
		"$action routes to PriceUpdateHandler (reads $expected_key)");
}

// An action we do not handle must fall through to the early return
$GLOBALS['mt2mba_test']['meta_reads'] = [];
$GLOBALS['mt2mba_test']['post_meta'] = [];
$product->handleBulkPriceAction('variable_stock', ['value' => '10'], 99, []);
t_assert(empty($GLOBALS['mt2mba_test']['meta_reads']) && empty($GLOBALS['mt2mba_test']['post_meta']),
	'an unhandled bulk action still does nothing at all');
//endregion

t_done();
