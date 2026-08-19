<?php
/**
 * The term save path must store the same value in any locale.
 *
 * Regression: handleTermMarkupSave() validated the posted value, then passed the
 * RESULT to sanitizeMarkupForStorage(), which validated it a second time. That
 * was harmless while validation was accidentally idempotent, but once
 * normalizeMarkupNotation() became separator-aware it stopped being so: the
 * first pass turns "1235,12" into internal "1235.12", and in a comma store the
 * second pass reads that '.' as a thousands separator and stores 123512.
 *
 * The unit tests all passed through this because they called validateMarkupValue()
 * once. This exercises the real hook, which is where the double call lived.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/utility/general.php';
require __DIR__ . '/../src/backend/term.php';

use mt2Tech\MarkupByAttribute\Backend\Term;

$GLOBALS['mt2mba_stub']['get_term'] = function ($term_id) {
	$term = new WP_Term();
	$term->term_id = $term_id;
	$term->name = 'Holstein';
	$term->taxonomy = 'pa_cows';
	return $term;
};

$term_component = Term::get_instance();

/** Post a markup through the real hook and return what landed in term meta. */
$save = function ($typed, $separator) use ($term_component) {
	$GLOBALS['mt2mba_stub']['decimal_separator'] = $separator;
	$GLOBALS['mt2mba_test']['term_meta'] = [];
	$_POST = ['term_markup' => $typed, '_wpnonce' => 'testnonce'];

	$term_component->handleTermMarkupSave(123);

	foreach ($GLOBALS['mt2mba_test']['term_meta'] as $call) {
		if ($call[0] === 'update' && $call[2] === 'mt2mba_markup') return $call[3];
	}
	return null;	// nothing stored
};

//region Comma-decimal store — the reported bug
$comma_cases = [
	['1235,12',  '1235.12', 'comma decimal'],
	['1.235,12', '1235.12', 'dot thousands, comma decimal (German/Spanish)'],
	['1 235,12', '1235.12', 'space thousands, comma decimal (French)'],
	['5,00',     '5',       'trailing zeros trimmed'],
	['-2,5 %',   '-2.5%',   'negative comma-decimal percentage, spaced'],
	['%50',      '50%',     'Turkish leading percent'],
	['5 %',      '5%',      'space before percent'],
];
foreach ($comma_cases as [$typed, $expected, $note]) {
	$stored = $save($typed, ',');
	t_assert($stored === $expected,
		sprintf("comma store: '%s' stores '%s' (got %s) — %s",
			$typed, $expected, var_export($stored, true), $note));
}
//endregion

//region Dot-decimal store — the same values in the other notation
$dot_cases = [
	['1235.12',  '1235.12', 'dot decimal'],
	['1,235.12', '1235.12', 'comma thousands, dot decimal (US/UK)'],
	['1 235.12', '1235.12', 'space thousands, dot decimal'],
	['5.00',     '5',       'trailing zeros trimmed'],
	['-2.5 %',   '-2.5%',   'negative dot-decimal percentage, spaced'],
	['%50',      '50%',     'Turkish leading percent'],
];
foreach ($dot_cases as [$typed, $expected, $note]) {
	$stored = $save($typed, '.');
	t_assert($stored === $expected,
		sprintf("dot store:   '%s' stores '%s' (got %s) — %s",
			$typed, $expected, var_export($stored, true), $note));
}
//endregion

//region Garbage is still discarded, in both stores
foreach ([',', '.'] as $separator) {
	// '1,235.12' in a comma store (and its mirror in a dot store) puts the
	// grouping mark after the decimal point — malformed, not merely foreign
	$wrong_order = ($separator === ',') ? '1,235.12' : '1.235,12';
	foreach (['abc', '5abc', '2-', '%50%', $wrong_order] as $garbage) {
		$stored = $save($garbage, $separator);
		t_assert($stored === null,
			sprintf("[%s] store: '%s' is discarded, nothing written to term meta (got %s)",
				$separator, $garbage, var_export($stored, true)));
	}
}
//endregion

//region Saving twice stores the same thing — no drift on re-save
$GLOBALS['mt2mba_stub']['decimal_separator'] = ',';
$first = $save('1.235,12', ',');
$second = $save('1.235,12', ',');
t_assert($first === $second && $first === '1235.12',
	"re-saving the same term does not drift the stored value (got '$first' then '$second')");
//endregion

t_done();
