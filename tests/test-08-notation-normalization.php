<?php
/**
 * Markup notation normalization — one canonical form, agreed on both sides.
 *
 * Canonical form is [-]digits[.digits][%]: no whitespace, no thousands
 * separators, sign leading only, '.' decimal, percent sign U+0025 trailing.
 *
 * The bug that prompted this: the client-side validator tested the raw value
 * against the canonical pattern while the server normalized first, so "5 %" —
 * which WooCommerce accepts — was rejected in the browser with a red box and
 * never reached PHP at all. Three definitions of a valid markup (JS, PHP,
 * WooCommerce) disagreed.
 *
 * tests/fixtures/notation-table.json is the single source of truth. This test
 * runs it through the PHP normalizer, then shells out to node to run the SAME
 * table through the JS normalizer lifted out of the shipping plugin file, and
 * asserts the two agree case for case. Add notations to the table, not here.
 *
 * Every case carries the store's decimal separator, because "1.235,12" is
 * correct German and meaningless in a dot store. Both configurations are
 * exercised; the JS receives the separator exactly as wp_localize_script()
 * delivers it at runtime.
 *
 * If node is unavailable the JS half is reported as a FAILURE, not skipped
 * quietly — an unverified mirror is the whole problem this test exists for.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/utility/general.php';

use mt2Tech\MarkupByAttribute\Utility\General;

$table = json_decode(file_get_contents(__DIR__ . '/fixtures/notation-table.json'), true);
$cases = $table['cases'];

$show = function ($value) {
	if ($value === false) return 'REJECTED';
	if ($value === null) return 'null';
	return "'" . $value . "'";
};

//region PHP normalizer matches the table, under each store's separator
foreach ($cases as $case) {
	$actual = General::normalizeMarkupNotation(trim($case['input']), $case['separator']);
	t_assert($actual === $case['normalized'],
		sprintf("PHP normalize %-12s [%s] -> %-11s (%s)",
			"'" . $case['input'] . "'", $case['separator'], $show($actual), $case['note']));
}
//endregion

//region Normalization is idempotent
// The browser normalizes the field before submitting and the server normalizes
// again on arrival, so a second pass must be a no-op. When it was not, the JS
// rewrote "1.235,12" to "1235.12" and the server then read that '.' as a
// thousands separator and stored 123512.
foreach ($cases as $case) {
	$once = General::normalizeMarkupNotation(trim($case['input']), $case['separator']);
	$twice = General::normalizeMarkupNotation($once, $case['separator']);
	t_assert($twice === $once,
		sprintf("idempotent %-12s [%s] -> %-11s stays put",
			"'" . $case['input'] . "'", $case['separator'], $show($once)));
}
//endregion

//region A round trip through the browser survives the server
// Simulates the real path: JS normalizes into the field, the field is posted,
// the server normalizes and stores. The stored value must match what a direct
// submission of the raw value would have stored.
foreach ($cases as $case) {
	if (empty($case['valid']) || !array_key_exists('stored', $case)) continue;
	$GLOBALS['mt2mba_stub']['decimal_separator'] = $case['separator'];

	$as_typed = General::validateMarkupValue($case['input']);
	$after_browser = General::validateMarkupValue(
		General::normalizeMarkupNotation(trim($case['input']), $case['separator'])
	);

	t_assert($after_browser === $as_typed,
		sprintf("round trip %-12s [%s] stores %s either way (got %s)",
			"'" . $case['input'] . "'", $case['separator'],
			$show($as_typed), $show($after_browser)));
}
$GLOBALS['mt2mba_stub']['decimal_separator'] = '.';
//endregion

//region validateMarkupValue accepts exactly what the table says, and stores what it says
foreach ($cases as $case) {
	$GLOBALS['mt2mba_stub']['decimal_separator'] = $case['separator'];
	$stored = General::validateMarkupValue($case['input']);

	t_assert(($stored !== false) === $case['valid'],
		sprintf("validate %-12s [%s] %s (%s)",
			"'" . $case['input'] . "'", $case['separator'],
			$case['valid'] ? 'accepted' : 'rejected', $case['note']));

	if ($case['valid'] && array_key_exists('stored', $case)) {
		t_assert($stored === $case['stored'],
			sprintf("store    %-12s [%s] -> %-11s (got %s)",
				"'" . $case['input'] . "'", $case['separator'],
				$show($case['stored']), $show($stored)));
	}
}
$GLOBALS['mt2mba_stub']['decimal_separator'] = '.';
//endregion

//region The regressions that started all of this
$GLOBALS['mt2mba_stub']['decimal_separator'] = '.';
t_assert(General::validateMarkupValue('5 %') === '5%',
	"'5 %' validates — the value that used to be blocked in the browser");
t_assert(General::validateMarkupValue('%50') === '50%',
	"'%50' validates to '50%' — Turkish notation reaches the same place");
t_assert(General::validateMarkupValue('2-') === false,
	"'2-' is rejected rather than silently read as +2 the way WooCommerce reads it");
t_assert(General::validateMarkupValue('5abc') === false,
	"'5abc' is rejected — wc_format_decimal() used to strip the letters and store 5");

$GLOBALS['mt2mba_stub']['decimal_separator'] = ',';
t_assert(General::validateMarkupValue('1.235,12') === '1235.12',
	"'1.235,12' validates in a comma store — German notation, wrongly rejected before");
t_assert(General::validateMarkupValue('1 235,12') === '1235.12',
	"'1 235,12' validates in a comma store — French notation");
$GLOBALS['mt2mba_stub']['decimal_separator'] = '.';
//endregion

//region JS normalizer agrees with the PHP one, case for case
$node = trim((string) shell_exec('node --version 2>&1'));
if (strpos($node, 'v') !== 0) {
	t_assert(false, 'node is available to verify the JS mirror (JS/PHP parity NOT checked)');
} else {
	$raw = shell_exec('node ' . escapeshellarg(__DIR__ . '/js-normalize-check.js') . ' 2>&1');
	$js = json_decode((string) $raw, true);

	if (!is_array($js)) {
		t_assert(false, 'node produced parseable output (got: ' . trim((string) $raw) . ')');
	} else {
		t_assert(count($js) === count($cases), 'node returned a result for every case');

		foreach ($cases as $i => $case) {
			$php_normalized = General::normalizeMarkupNotation(trim($case['input']), $case['separator']);

			$GLOBALS['mt2mba_stub']['decimal_separator'] = $case['separator'];
			$php_valid = (General::validateMarkupValue($case['input']) !== false);

			t_assert(isset($js[$i]) && $js[$i]['normalized'] === $php_normalized,
				sprintf("JS==PHP normalize %-12s [%s] -> %s",
					"'" . $case['input'] . "'", $case['separator'], $show($php_normalized)));

			t_assert(isset($js[$i]) && $js[$i]['valid'] === $php_valid,
				sprintf("JS==PHP validity %-12s [%s] is %s",
					"'" . $case['input'] . "'", $case['separator'], $php_valid ? 'valid' : 'invalid'));
		}
		$GLOBALS['mt2mba_stub']['decimal_separator'] = '.';
	}
}
//endregion

t_done();
