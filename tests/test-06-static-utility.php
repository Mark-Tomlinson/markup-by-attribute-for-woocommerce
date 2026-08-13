<?php
/**
 * Item 6 — Utility\General is a stateless static helper; plugin bootstrap
 * (settings constants, schema stamping) belongs to the main plugin file.
 *
 * Pure refactor: no behavior may change. This is the after-test — it pins
 * the new shape (no singleton, no `global $mt2mba_utility`) and characterizes
 * every helper's output so a later phase can't silently drift them.
 *
 * The bootstrap constants block is skipped here because this test calls the
 * real define_constants(), which uses bare define().
 */
$GLOBALS['mt2mba_skip_plugin_constants'] = true;
require __DIR__ . '/bootstrap.php';

use mt2Tech\MarkupByAttribute\Utility\General;

//region Bootstrap moved out of the constructor
// Loading the plugin file registers the autoloader and declares the functions;
// mt2mba_main() itself is only wired to a hook, so nothing else runs.
require __DIR__ . '/../markup-by-attribute-for-woocommerce.php';

t_assert(function_exists('mt2Tech\MarkupByAttribute\mt2mba_stamp_schema_version'),
	'schema stamping has its own named function');

mt2Tech\MarkupByAttribute\define_constants();

$settings_constants = [
	'MT2MBA_DESC_BEHAVIOR'       => 'append',
	'MT2MBA_DROPDOWN_BEHAVIOR'   => 'add',
	'MT2MBA_INCLUDE_ATTRB_NAME'  => 'no',
	'MT2MBA_HIDE_BASE_PRICE'     => 'no',
	'MT2MBA_SALE_PRICE_MARKUP'   => 'yes',
	'MT2MBA_ROUND_MARKUP'        => 'no',
	'MT2MBA_MAX_VARIATIONS'      => 50,
];
foreach ($settings_constants as $name => $expected) {
	t_assert(defined($name) && constant($name) === $expected,
		"define_constants() defines $name (Settings default)");
}
t_assert(defined('MT2MBA_CURRENCY_SYMBOL') && MT2MBA_CURRENCY_SYMBOL === '$',
	'define_constants() defines MT2MBA_CURRENCY_SYMBOL, HTML-decoded');

// Schema stamping: fresh install stamps, existing install is left alone
$GLOBALS['mt2mba_test']['options'] = [];
mt2Tech\MarkupByAttribute\mt2mba_stamp_schema_version();
t_assert(get_option('mt2mba_db_version') === MT2MBA_SCHEMA_VERSION,
	'fresh install is stamped with the current schema version');

$GLOBALS['mt2mba_test']['options']['mt2mba_db_version'] = '1.0';
$GLOBALS['mt2mba_test']['option_log'] = [];
mt2Tech\MarkupByAttribute\mt2mba_stamp_schema_version();
t_assert(get_option('mt2mba_db_version') === '1.0',
	'existing install keeps its schema version (upgrade runner still sees the gap)');
t_assert(empty($GLOBALS['mt2mba_test']['option_log']),
	'existing install writes no option at all');
//endregion

//region General is a pure static utility
$reflect = new ReflectionClass(General::class);

t_assert(!$reflect->hasMethod('get_instance'), 'General::get_instance() is gone');
t_assert(empty($reflect->getProperties()), 'General holds no state');
t_assert(!$reflect->hasMethod('__construct'), 'General has no constructor to run bootstrap in');

$non_static = [];
foreach ($reflect->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
	if (!$method->isStatic()) $non_static[] = $method->getName();
}
t_assert(empty($non_static),
	'every public method is static (' . (empty($non_static) ? 'all' : implode(', ', $non_static)) . ')');

// The global itself must be gone from every shipped PHP file
$shipped = [];
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/../src'));
foreach ($iterator as $file) {
	if ($file->isFile() && $file->getExtension() === 'php') $shipped[] = $file->getPathname();
}
$shipped[] = __DIR__ . '/../markup-by-attribute-for-woocommerce.php';

$offenders = [];
foreach ($shipped as $file) {
	if (strpos(file_get_contents($file), 'mt2mba_utility') !== false) {
		$offenders[] = basename($file);
	}
}
t_assert(empty($offenders),
	'no shipped file references $mt2mba_utility' . ($offenders ? ' (' . implode(', ', $offenders) . ')' : ''));
//endregion

//region Characterization — helper output must not drift
t_assert(General::cleanUpPrice('5') === '$5.00', 'cleanUpPrice formats a fixed amount as currency');
t_assert(General::cleanUpPrice('-5') === '$5.00', 'cleanUpPrice returns the absolute value');
t_assert(General::cleanUpPrice('10%') === '10%', 'cleanUpPrice passes a percentage through');

t_assert(General::formatOptionMarkup('5') === ' (+$5.00)', 'formatOptionMarkup signs a positive amount');
t_assert(General::formatOptionMarkup('-5') === ' (-$5.00)', 'formatOptionMarkup signs a negative amount');
// Word form since 4.7.0 — see test-22. Not reachable in production: the meta
// the dropdown reads is always a resolved currency amount.
t_assert(General::formatOptionMarkup('10%') === ' (Add 10%)', 'formatOptionMarkup words a percentage');
t_assert(General::formatOptionMarkup('') === '', 'formatOptionMarkup returns empty for no markup');
t_assert(General::formatOptionMarkup('0') === '', 'formatOptionMarkup returns empty for a zero markup');

t_assert(General::formatVariationMarkupDescription('5', 'Color', 'Blue') === 'Add $5.00 for Blue',
	'formatVariationMarkupDescription builds the Add line');
t_assert(General::formatVariationMarkupDescription('-5', 'Color', 'Blue') === 'Subtract $5.00 for Blue',
	'formatVariationMarkupDescription builds the Subtract line');

t_assert(General::removeBracketedString('<b>', '</b>', 'keep <b>drop</b> keep') === 'keep  keep',
	'removeBracketedString strips the bracketed section and its markers');
t_assert(General::removeBracketedString('<b>', '</b>', 'nothing to do') === 'nothing to do',
	'removeBracketedString is a no-op when the markers are absent');

t_assert(General::stripMarkupAnnotation('Blue (Add $5.00)') === 'Blue',
	'stripMarkupAnnotation removes an Add annotation');
t_assert(General::stripMarkupAnnotation('Blue (Subtract 10%)') === 'Blue',
	'stripMarkupAnnotation removes a Subtract annotation');
t_assert(General::stripMarkupAnnotation('Blue') === 'Blue',
	'stripMarkupAnnotation leaves a clean name alone');

// Sign form for currency since 4.7.0, and the $is_negative parameter is gone — see test-22
t_assert(General::addMarkupToName('Blue', '5') === 'Blue (+$5.00)', 'addMarkupToName signs a positive amount');
t_assert(General::addMarkupToName('Blue', '-5') === 'Blue (-$5.00)', 'addMarkupToName signs a negative amount');
t_assert(General::addMarkupToTermDescription('Nice color', '5') === "Nice color\n(Add \$5.00)",
	'addMarkupToTermDescription appends on a new line');

t_assert(General::validateMarkupValue('5.00') === '5', 'validateMarkupValue trims trailing zeros');
t_assert(General::validateMarkupValue('10%') === '10%', 'validateMarkupValue keeps the percent sign');
t_assert(General::validateMarkupValue('-2.5') === '-2.5', 'validateMarkupValue keeps a negative sign');
t_assert(General::validateMarkupValue('') === '', 'validateMarkupValue treats empty as no markup');
t_assert(General::validateMarkupValue('0') === '', 'validateMarkupValue treats zero as no markup');
t_assert(General::validateMarkupValue('abc') === false, 'validateMarkupValue rejects garbage');
t_assert(General::validateMarkupValue('5%%') === false, 'validateMarkupValue rejects a double percent');

t_assert(General::sanitizeMarkupForStorage('5.00') === '5', 'sanitizeMarkupForStorage validates then sanitizes');
t_assert(General::sanitizeMarkupForStorage('abc') === '', 'sanitizeMarkupForStorage discards invalid input');
// sanitizeMarkupForDisplay() was removed in item 10 — it escaped rather than
// sanitized, and every caller escaped again. See test-13.
//endregion

t_done();
