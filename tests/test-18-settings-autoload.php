<?php
/**
 * Item 12 — characterization test: the exact autoload SQL a settings save emits.
 *
 * Relocating that write out of the woocommerce_get_settings_products READ filter
 * is behavior-preserving, so there is no bug to reproduce red-then-green. This
 * file is the substitute, in the mold of test-10: it pins the statements, in
 * order, that the current code issues. Written green against cc26280 BEFORE the
 * refactor, so any later diff in these strings is a regression.
 *
 * When the write relocates, ONLY t_trigger_save() below changes — it is the one
 * seam that knows *where* the write is hooked. Every assertion must survive
 * untouched, including the option-name list, which is the thing at risk when
 * runtime array_filter harvesting is replaced by an explicit list.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/backend/settings.php';

use mt2Tech\MarkupByAttribute\Backend\Settings;

$settings_component = Settings::get_instance();

/**
 * Drive a settings save and return the SQL it emitted.
 *
 * THE SEAM — the only thing in this file that knows *where* the write is hooked.
 * It used to call getSettings() with $_POST['save'] set, because the write lived
 * inside the read filter. It now calls the handler WooCommerce fires after
 * save_fields(). Every assertion below survived that move untouched.
 */
function t_trigger_save(string $section = 'mt2mba'): array {
	$GLOBALS['wpdb'] = new MT2MBA_Fake_WPDB();
	// Fire the hook by name rather than calling the handler, so a typo in either
	// the registration or the hook name fails here. WC composes the name as
	// woocommerce_update_options_{tab}_{section} and fires it only for the
	// section actually being saved.
	do_action('woocommerce_update_options_products_' . $section);
	return $GLOBALS['wpdb']->queries;
}

//region The option names a save touches
$queries = t_trigger_save();

$names = [];
foreach ($queries as $sql) {
	if (preg_match("/option_name = '([^']+)'/", $sql, $m)) $names[] = $m[1];
}

// Pinned deliberately as a literal list, not derived from the settings array:
// step 3 of item 12 replaces the runtime array_filter/array_column harvesting
// with an explicit list, and this is what proves the two agree.
$expected_names = [
	'mt2mba_dropdown_behavior',
	'mt2mba_desc_behavior',
	'mt2mba_include_attrb_name',
	'mt2mba_hide_base_price',
	'mt2mba_sale_price_markup',
	'mt2mba_round_markup',
	'mt2mba_max_variations',
];

t_assert($names === $expected_names,
	'a save touches exactly the mt2mba_* options, in order (' . implode(', ', $names) . ')');
//endregion

//region The exact statement issued for each
// Whitespace is collapsed before comparing: relocating the write changes its
// nesting depth, and indentation is not behavior. Everything else is pinned
// byte-for-byte — a changed table, a dropped WHERE, a different autoload value
// or a reordered statement all still fail.
$normalize = fn(string $sql): string => trim(preg_replace('/\s+/', ' ', $sql));

$expected_sql = [];
foreach ($expected_names as $name) {
	$expected_sql[] = "UPDATE wp_options SET autoload = 'no' WHERE option_name = '$name'";
}

t_assert(array_map($normalize, $queries) === $expected_sql,
	'each is a single UPDATE setting autoload to the back-compatible \'no\'');

// 'no' rather than 'off': min-WP is 5.7, and WP 6.6+ still reads 'no' as
// not-autoloaded. Pinned because the value is the whole point of the exercise.
$wrong_value = array_filter($queries, fn($sql) => strpos($sql, "autoload = 'no'") === false);
t_assert(empty($wrong_value), 'every statement writes the back-compatible \'no\'');
//endregion

//region A save must not reach past this plugin's own options
$unscoped = array_filter($queries,
	fn($sql) => strpos($sql, 'wp_options') === false || strpos($sql, "WHERE option_name = 'mt2mba_") === false);
t_assert(empty($unscoped), 'every statement is scoped to one named mt2mba_ option in wp_options');

t_assert(count($queries) === 7, 'seven statements, one per option (' . count($queries) . ')');
//endregion

//region The read filter is a read filter
// The point of item 12: building the settings array must not write, no matter
// what the request looks like. $_POST['save'] used to be the trigger, so it is
// the case worth stating explicitly.
$GLOBALS['wpdb'] = new MT2MBA_Fake_WPDB();
$_POST = ['save' => 'Save changes'];
$settings_component->getSettings([], 'mt2mba');
t_assert($GLOBALS['wpdb']->queries === [],
	'getSettings() writes nothing, even on a request that looks like a save');

$GLOBALS['wpdb'] = new MT2MBA_Fake_WPDB();
$_POST = [];
$settings_component->getSettings([], 'mt2mba');
t_assert($GLOBALS['wpdb']->queries === [], 'rendering the page writes nothing');

$returned = $settings_component->getSettings(['someone' => 'else'], 'inventory');
t_assert($returned === ['someone' => 'else'], 'another section gets its settings back untouched');

// Saving a different Products section must not drag our options along
t_assert(t_trigger_save('inventory') === [],
	'saving another section writes nothing');
//endregion

t_done();
