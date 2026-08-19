<?php
/**
 * Markup annotation consistency — one rule, driven by the markup TYPE.
 *
 * Before this, a product mixing "Add Markup to Name?" on and off spoke two
 * languages about the same markup: the dropdown said "(-$1.67)" and the baked-in
 * term name said "(Subtract 5%)". The rule now follows the type, not the place:
 *
 *   percentage -> word form   (Add 5%) / (Subtract 5%)   — bare "-5%" is ambiguous
 *   currency   -> sign form   (+$5.00) / (-$5.00)        — the symbol says the rest
 *
 * Decided with Mark 2026-06-17, built 2026-08-13. Two deliberate limits, both
 * his call and both pinned below so a later "consistency" pass does not quietly
 * undo them:
 *
 *   - addMarkupToTermDescription() is NOT converted. Term descriptions keep the
 *     word form for both types.
 *   - stripMarkupAnnotation() recognizes the sign form only at the END of the
 *     string, which is the only place addMarkupToName() ever puts it. That keeps
 *     "Widget (-5) Blue" intact at the cost of stripping a name that ends in a
 *     bare signed number.
 *
 * Run with no arguments for the full suite; it re-executes itself to cover the
 * dropdown behaviors, which are constants and so cannot vary within one process:
 *
 *   php test-22-annotation-consistency.php [behavior] [currency-symbol]
 */

// Dropdown behavior and currency symbol are define()d constants, so a child
// process is the only way to exercise more than one of each.
$behavior = $argv[1] ?? null;
$symbol   = $argv[2] ?? '$';
if ($behavior !== null) {
	define('MT2MBA_DROPDOWN_BEHAVIOR', $behavior);
	define('MT2MBA_CURRENCY_SYMBOL', $symbol);
}

require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/utility/general.php';

use mt2Tech\MarkupByAttribute\Utility\General;

/** Swedish-style suffix symbol: "1.234,50 kr" — the case a '$' prefix cannot reach. */
function t_use_suffix_currency(): void {
	$GLOBALS['mt2mba_stub']['price_format'] = function ($price) {
		return number_format($price, 2, ',', '.') . ' kr';
	};
}

//region Child mode — one dropdown behavior per process
if ($behavior !== null) {
	if ($symbol === 'kr') t_use_suffix_currency();

	if ($behavior === 'hide') {
		t_assert(General::formatOptionMarkup('5') === '',
			"[hide] a positive amount produces nothing");
		t_assert(General::formatOptionMarkup('-5') === '',
			"[hide] a negative amount produces nothing");
		t_assert(General::formatOptionMarkup('10%') === '',
			"[hide] a percentage produces nothing");

	} elseif ($behavior === 'add') {
		// The symbol stays. Pinned for the suffix locale because that is where
		// an over-eager space-squeeze would show up as " (+5,00kr)".
		t_assert(General::formatOptionMarkup('5') === ' (+5,00 kr)',
			"[add/$symbol] suffix symbol is kept, spacing intact (got '"
				. General::formatOptionMarkup('5') . "')");
		t_assert(General::formatOptionMarkup('-1234.5') === ' (-1.234,50 kr)',
			"[add/$symbol] grouping and symbol both survive (got '"
				. General::formatOptionMarkup('-1234.5') . "')");
		t_assert(General::formatOptionMarkup('10%') === ' (Add 10%)',
			"[add/$symbol] a percentage still reads as a word");

	} elseif ($symbol === 'kr') {
		// Suffix-symbol locale: stripping "kr" leaves a space that must go with it
		t_assert(General::formatOptionMarkup('5') === ' (+5,00)',
			"[$behavior/kr] suffix symbol is removed and the gap closed (got '"
				. General::formatOptionMarkup('5') . "')");
		t_assert(General::formatOptionMarkup('-1234.5') === ' (-1.234,50)',
			"[$behavior/kr] grouping survives the symbol strip (got '"
				. General::formatOptionMarkup('-1234.5') . "')");
		t_assert(General::formatOptionMarkup('10%') === ' (Add 10%)',
			"[$behavior/kr] a percentage is untouched by the symbol strip");

	} else {
		t_assert(General::formatOptionMarkup('5') === ' (+5.00)',
			"[$behavior] prefix symbol is removed (got '" . General::formatOptionMarkup('5') . "')");
		t_assert(General::formatOptionMarkup('-5') === ' (-5.00)',
			"[$behavior] a negative amount keeps its sign without the symbol");
		t_assert(General::formatOptionMarkup('10%') === ' (Add 10%)',
			"[$behavior] a percentage still reads as a word");
	}

	t_done();
}
//endregion

//region The core rule — four quadrants
t_assert(General::formatMarkupAnnotation('5') === '(+$5.00)',
	'currency, positive -> sign form (got ' . General::formatMarkupAnnotation('5') . ')');
t_assert(General::formatMarkupAnnotation('-5') === '(-$5.00)',
	'currency, negative -> sign form (got ' . General::formatMarkupAnnotation('-5') . ')');
t_assert(General::formatMarkupAnnotation('5%') === '(Add 5%)',
	'percentage, positive -> word form (got ' . General::formatMarkupAnnotation('5%') . ')');
t_assert(General::formatMarkupAnnotation('-5%') === '(Subtract 5%)',
	'percentage, negative -> word form (got ' . General::formatMarkupAnnotation('-5%') . ')');

// The sign lives in the value; nothing else has to be told about it
t_assert(General::formatMarkupAnnotation('') === '', 'no markup annotates nothing');
t_assert(General::formatMarkupAnnotation('0') === '', 'a zero markup annotates nothing');
t_assert(General::formatMarkupAnnotation('0%') === '', 'a zero percentage annotates nothing');

// PHP 7.4 vs 8 change how a non-numeric string compares to 0. BackRev runs
// 7.4.3, so the guard is pinned rather than trusted.
t_assert(General::formatMarkupAnnotation('10%') === '(Add 10%)',
	'a percentage is not mistaken for zero (the PHP 7 string-to-int comparison trap)');
//endregion

//region addMarkupToName follows the type, and no longer needs to be told the sign
t_assert(General::addMarkupToName('Blue', '5') === 'Blue (+$5.00)',
	'name, currency positive (got ' . General::addMarkupToName('Blue', '5') . ')');
t_assert(General::addMarkupToName('Blue', '-5') === 'Blue (-$5.00)',
	'name, currency negative (got ' . General::addMarkupToName('Blue', '-5') . ')');
t_assert(General::addMarkupToName('Blue', '5%') === 'Blue (Add 5%)',
	'name, percentage positive');
t_assert(General::addMarkupToName('Blue', '-5%') === 'Blue (Subtract 5%)',
	'name, percentage negative');
t_assert(General::addMarkupToName('Blue', '') === 'Blue',
	'no markup leaves the name alone, with no trailing space');

// The redundant $is_negative parameter is gone: the sign was always already in
// the value, and term.php derived the flag only to hand it straight back.
$params = (new ReflectionMethod(General::class, 'addMarkupToName'))->getNumberOfParameters();
t_assert($params === 2, "addMarkupToName takes 2 parameters, not 3 (got $params)");

$term_src = file_get_contents(__DIR__ . '/../src/backend/term.php');
t_assert(strpos($term_src, 'addMarkupToName($new_name, $markup, $is_negative)') === false,
	'term.php no longer passes $is_negative to addMarkupToName');
//endregion

//region Term DESCRIPTIONS deliberately keep the word form (Mark's call, 2026-08-13)
t_assert(General::addMarkupToTermDescription('Nice color', '5') === "Nice color\n(Add \$5.00)",
	'description, currency positive stays in the word form');
t_assert(General::addMarkupToTermDescription('Nice color', '-5', true) === "Nice color\n(Subtract \$5.00)",
	'description, currency negative stays in the word form');
t_assert(General::addMarkupToTermDescription('Nice color', '5%') === "Nice color\n(Add 5%)",
	'description, percentage unchanged');

$desc_params = (new ReflectionMethod(General::class, 'addMarkupToTermDescription'))->getNumberOfParameters();
t_assert($desc_params === 3,
	"addMarkupToTermDescription keeps its \$is_negative parameter (got $desc_params)");
//endregion

//region Round trip — everything addMarkupToName writes, stripMarkupAnnotation removes
// This is the gotcha the plan doc called out: the stripper only knew the word
// patterns, so once currency markups started baking in as "(-$5.00)" the old
// annotation would survive a markup change and accumulate.
foreach (['5', '-5', '5%', '-5%', '1234.56', '-0.99'] as $markup) {
	$annotated = General::addMarkupToName('Blue', $markup);
	t_assert(General::stripMarkupAnnotation($annotated) === 'Blue',
		"round trip '$markup': '$annotated' strips back to 'Blue'");
}

// Two annotations must never survive a re-save
t_assert(General::addMarkupToName(General::stripMarkupAnnotation('Blue (+$5.00)'), '-2%')
		=== 'Blue (Subtract 2%)',
	're-annotating replaces rather than appends');

// Names baked by an earlier version keep the word form until the term is next
// saved (no upgrade routine — Mark's call, 2026-08-13), so the stripper still
// has to recognize them.
t_assert(General::stripMarkupAnnotation('Blue (Add $5.00)') === 'Blue',
	'a pre-4.7.0 word-form currency annotation still strips');
t_assert(General::stripMarkupAnnotation('Blue (Subtract $5.00)') === 'Blue',
	'a pre-4.7.0 word-form negative currency annotation still strips');
t_assert(General::stripMarkupAnnotation('Blue (Subtract 10%)') === 'Blue',
	'a word-form percentage annotation still strips');
t_assert(General::stripMarkupAnnotation('Blue') === 'Blue',
	'a clean name is left alone');
//endregion

//region End-anchoring is what keeps the sign form off real term names
t_assert(General::stripMarkupAnnotation('Widget (-5) Blue') === 'Widget (-5) Blue',
	'a signed number mid-name is NOT an annotation');
t_assert(General::stripMarkupAnnotation('Blue (2 pack)') === 'Blue (2 pack)',
	'a parenthetical with no sign is left alone');
t_assert(General::stripMarkupAnnotation('Blue (+extras)') === 'Blue (+extras)',
	'a signed parenthetical with no digits is left alone');
t_assert(General::stripMarkupAnnotation('Cable (2m)') === 'Cable (2m)',
	'a measurement is left alone');

// Documented false positive, accepted 2026-08-13: a name ending in a bare
// signed number is indistinguishable from a symbol-less annotation.
t_assert(General::stripMarkupAnnotation('Thermostat (-40)') === 'Thermostat',
	'KNOWN: a name ending in a bare signed number is stripped');
//endregion

//region What the dropdown actually sees in production
// bulkSaveProductMarkupValues() writes number_format(floatval(...)) into
// mt2mba_{term}_markup_amount (pricesethandler.php), so formatOptionMarkup()
// is only ever handed a resolved currency amount — never a percentage. The
// word-form branch below is defined for correctness, not because it renders.
t_assert(General::formatOptionMarkup('5.00') === ' (+$5.00)',
	'[add] dropdown signs a positive amount, unchanged from 4.6.3');
t_assert(General::formatOptionMarkup('-1.67') === ' (-$1.67)',
	'[add] dropdown signs a negative amount, unchanged from 4.6.3');
t_assert(General::formatOptionMarkup('') === '', '[add] no markup, no annotation');
t_assert(General::formatOptionMarkup('0') === '', '[add] zero markup, no annotation');

$handler_src = file_get_contents(__DIR__ . '/../src/backend/handlers/pricesethandler.php');
t_assert(strpos($handler_src, "number_format(floatval(\$details['markup'])") !== false,
	'the markup meta the dropdown reads is still written as a plain decimal');
//endregion

//region The other dropdown behaviors, one child process each
foreach ([['hide', '$'], ['no_symbol', '$'], ['no_symbol', 'kr'], ['add', 'kr']] as $mode) {
	echo "  --- dropdown behavior '{$mode[0]}', currency '{$mode[1]}'\n";
	passthru(
		escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' '
			. escapeshellarg($mode[0]) . ' ' . escapeshellarg($mode[1]),
		$exit_code
	);
	t_assert($exit_code === 0, "dropdown behavior '{$mode[0]}' with '{$mode[1]}' behaves");
}
//endregion

t_done();
