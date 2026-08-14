<?php
/**
 * Item 24 — the "Reapply markups to prices" bulk action must survive a panel rebuild.
 *
 * The bug (reproduced by Mark on BOTH WPDev/WC 11.0.0 and BackRev/WC 5.0.0, so it is
 * as old as the feature): open a variable product's [Variations] tab and the action is
 * there; click [Save attributes] on the [Attributes] tab and it is gone; reload the page
 * and it is back. WooCommerce's save_attributes handler does
 *
 *     $('#variable_product_options').load( page + ' #variable_product_options_inner', ... )
 *
 * which replaces the entire panel — bulk-actions <select> included — with fresh server
 * HTML. The option was injected once at document.ready and never again.
 *
 * ⚠️ WHAT THIS TEST DOES NOT DO. It cannot execute the fix. The behavior is jQuery
 * event timing against a DOM that only a browser has, and jsdom would be a dependency
 * this plugin does not carry. Item 24 is verified in a browser or not at all; the
 * reproduction above IS the test, on WPDev and BackRev both.
 *
 * What it does do is guard two failure modes that are SILENT — no error, no console
 * warning, just a menu entry quietly missing:
 *
 *   1. the re-injection wiring being dropped or reverted, and
 *   2. the localized i18n keys drifting apart between PHP and JS, which would render
 *      the option with empty text.
 */
require __DIR__ . '/bootstrap.php';

$js  = file_get_contents(__DIR__ . '/../src/js/jq-mt2mba-reapply-markups-product.js');
$php = file_get_contents(__DIR__ . '/../src/backend/product.php');

//region The injection has to be re-runnable, and actually re-run
t_assert(preg_match('/function\s+addReapplyMarkupOption\s*\(/', $js) === 1,
	'the injection lives in a named function rather than inline in document.ready');

// Must match a BINDING, not a mention: this file also TRIGGERS that same event after a
// reapply, so a plain substring test passes against the unfixed code and proves nothing.
t_assert(preg_match('/\.on\(\s*\'woocommerce_variations_loaded/', $js) === 1,
	're-injects on woocommerce_variations_loaded (fires on #woocommerce-product-data)');

t_assert(preg_match('/\.on\(\s*[\'"]reload[\'"]\s*,\s*[\'"]#variable_product_options[\'"]/', $js) === 1,
	"re-injects on 'reload', which is what save_attributes triggers after replacing the panel");

// Both events can fire for a single rebuild, and 'reload' is delegated, so the
// function must be safe to call repeatedly or the menu grows a duplicate each time.
t_assert(preg_match('/option\[value="reapply_markup"\]\'\s*\)\.length\)\s*return/', $js) === 1,
	'the function bails when the option is already present (no duplicates)');
//endregion

//region Positional optgroup targeting is gone
// WooCommerce has added groups to this menu more than once — WC 11.0.0 ships a
// conditional 'Cost of goods' group. optgroup.eq(1) was correct only by luck.
t_assert(strpos($js, "optgroup').eq(") === false && strpos($js, 'optgroup").eq(') === false,
	'the Pricing group is no longer selected by position');

t_assert(strpos($js, 'option[value="variable_regular_price"]') !== false,
	"the Pricing group is anchored on WooCommerce's own 'Set regular prices' action");

// That anchor is only stable because it is the same string the PHP side dispatches on
t_assert(strpos($php, "'variable_regular_price'") !== false || strpos($php, '"variable_regular_price"') !== false,
	'variable_regular_price is still the action value product.php knows');
//endregion

//region Every i18n key the JS reads is one the PHP actually defines
// A miss here is invisible: mt2mbaLocal.i18n.<typo> is undefined, and
// $('<option>', {text: undefined}) renders a blank menu entry rather than throwing.
// Note 'reapplyMarkupss' really does carry two s's on BOTH sides — a matched pair
// since v4.3.3 (dccd939). Ugly, harmless, and this test is what keeps it matched.
preg_match_all('/\'(\w+)\'\s*=>\s*__\(/', $php, $php_matches);
$defined = array_unique($php_matches[1]);

preg_match_all('/mt2mbaLocal\.i18n\.(\w+)/', $js, $js_matches);
$used = array_unique($js_matches[1]);

t_assert(count($used) > 0, 'the JS reads at least one localized string (guards the regex itself)');

foreach ($used as $key) {
	t_assert(in_array($key, $defined, true),
		"JS reads mt2mbaLocal.i18n.$key and product.php defines it");
}

// And the reverse, for the keys the JS actually consumes
$localized_block = strstr($php, "'i18n' => array(");
foreach ($used as $key) {
	t_assert($localized_block !== false && strpos($localized_block, "'$key'") !== false,
		"product.php localizes $key inside the i18n block, not elsewhere");
}

// KNOWN, recorded rather than asserted-in-place: 'failedRecalculating' is defined at
// product.php:87 and read by no JS at all. It is shipped to ten locales and can never
// be displayed, because the AJAX calls it was written for have no error: handler (see
// item 25). Deliberately NOT pinned here — pinning it would make removing the dead
// string fail this test for the wrong reason.
$dead = array_values(array_diff($defined, $used));
t_assert(in_array('failedRecalculating', $dead, true) || in_array('failedRecalculating', $used, true),
	'failedRecalculating is accounted for — currently unused (item 25), wire it up or drop it');
//endregion

t_done();
