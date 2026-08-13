<?php
/**
 * Item 13 — characterization of handleTermMarkupSave()
 *
 * Nothing is broken here. The 110-line method is being split into
 * verifyTermSaveNonce() and maybeRewriteTermNameAndDesc(), so red-then-green
 * would be theater. Instead this pins what the outside world can observe from
 * every path — the ordered term-meta calls and the exact wp_update_term()
 * arguments — and the same trace must come back after the split.
 *
 * Written and green against 145a791 BEFORE any src change.
 *
 * Deliberately NOT pinned: which reads happen on the re-entrant pass. The
 * refactor moves the $is_rewriting_term guard ahead of get_term() and the nonce
 * check on purpose, so asserting on those calls would fail for a change we
 * chose. The observable trace is empty either way.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/utility/general.php';
require __DIR__ . '/../src/backend/term.php';

use mt2Tech\MarkupByAttribute\Backend\Term;

$term_component = Term::get_instance();

/** Raise or lower the private re-entrancy flag. */
function t19_set_rewriting(bool $value): void {
	$property = new ReflectionProperty(Term::class, 'is_rewriting_term');
	$property->setAccessible(true);
	$property->setValue(null, $value);
}

/** Render the recorded side effects as one comparable line. */
function t19_trace(): string {
	$steps = [];
	foreach ($GLOBALS['mt2mba_test']['term_meta'] as $call) {
		$steps[] = sprintf('%s_term_meta(%d,%s,%s)',
			$call[0], $call[1], $call[2], json_encode($call[3]));
	}
	foreach ($GLOBALS['mt2mba_test']['term_updates'] as $update) {
		$steps[] = sprintf('wp_update_term(%d,%s,name=%s,desc=%s)',
			$update[0], json_encode($update[1]),
			json_encode($update[2]['name']), json_encode($update[2]['description']));
	}
	return $steps ? implode(' | ', $steps) : '(no side effects)';
}

/**
 * Drive the real hook under one set of conditions and return the trace.
 *
 * Recognized keys: post, can, term ('missing'|'error'|[name, desc, taxonomy]),
 * name_flag, desc_flag, nonce (callable), rewriting.
 */
function t19_save(Term $term_component, array $case): string {
	$GLOBALS['mt2mba_test']['term_meta']    = [];
	$GLOBALS['mt2mba_test']['term_updates'] = [];
	$GLOBALS['mt2mba_test']['nonce_checks'] = [];

	$term_spec = $case['term'] ?? [];
	$GLOBALS['mt2mba_stub']['get_term'] = function ($term_id) use ($term_spec) {
		if ($term_spec === 'missing') return null;
		if ($term_spec === 'error')   return new WP_Error();
		$term = new WP_Term();
		$term->term_id     = $term_id;
		$term->name        = $term_spec['name'] ?? 'Holstein';
		$term->description = $term_spec['desc'] ?? 'A cow.';
		$term->taxonomy    = $term_spec['taxonomy'] ?? 'pa_cows';
		return $term;
	};

	// Only the lowercase taxonomy is mapped, so a handler that skips
	// sanitize_key() on a mixed-case taxonomy looks up attribute id 0
	$GLOBALS['mt2mba_stub']['taxonomy_ids'] = ['pa_cows' => 42];
	$GLOBALS['mt2mba_stub']['can']          = $case['can'] ?? true;
	$GLOBALS['mt2mba_stub']['nonce_ok']     = $case['nonce']
		?? function ($nonce, $action) { return $action === 'update-tag_123'; };

	$GLOBALS['mt2mba_test']['options'] = [
		MT2MBA_REWRITE_TERM_NAME_PREFIX . '42' => $case['name_flag'] ?? 'no',
		MT2MBA_REWRITE_TERM_DESC_PREFIX . '42' => $case['desc_flag'] ?? 'no',
	];

	$_POST = $case['post'] ?? ['term_markup' => '5.00', '_wpnonce' => 'goodedit'];

	t19_set_rewriting($case['rewriting'] ?? false);
	$term_component->handleTermMarkupSave(123);
	t19_set_rewriting(false);

	return t19_trace();
}

$accept_add  = function ($nonce, $action) { return $action === 'mt2mba_add_term'; };
$reject_all  = function ($nonce, $action) { return false; };
$saved_meta  = 'delete_term_meta(123,mt2mba_markup,null) | update_term_meta(123,mt2mba_markup,"5")';
$no_effects  = '(no side effects)';

//region Paths that must do nothing at all
$bail_cases = [
	'no markup field posted' => ['post' => ['_wpnonce' => 'goodedit']],
	'user cannot manage product terms' => ['can' => false],
	'term no longer exists' => ['term' => 'missing'],
	'get_term() returned WP_Error' => ['term' => 'error'],
	'edit nonce invalid' => ['nonce' => $reject_all],
	'add nonce invalid' => [
		'post'  => ['term_markup' => '5.00', 'mt2mba_term_nonce' => 'badadd'],
		'nonce' => $reject_all,
	],
	'no nonce posted at all' => ['post' => ['term_markup' => '5.00']],
	're-entrant pass during wp_update_term()' => [
		'name_flag' => 'yes',
		'rewriting' => true,
	],
];
foreach ($bail_cases as $label => $case) {
	$actual = t19_save($term_component, $case);
	t_assert($actual === $no_effects,
		$actual === $no_effects ? "$label — no side effects" : "$label — expected none, got: $actual");
}

// Both nonces posted: the edit nonce is checked and its verdict is final
$both = t19_save($term_component, [
	'post'  => ['term_markup' => '5.00', '_wpnonce' => 'badedit', 'mt2mba_term_nonce' => 'goodadd'],
	'nonce' => $accept_add,
	'name_flag' => 'yes',
]);
t_assert($both === $no_effects, 'both nonces posted — edit nonce loses, save rejected');
t_assert($GLOBALS['mt2mba_test']['nonce_checks'] === [['badedit', 'update-tag_123']],
	'both nonces posted — only the edit nonce is verified, exactly once');
//endregion

//region The add path
$add = t19_save($term_component, [
	'post'      => ['term_markup' => '5.00', 'mt2mba_term_nonce' => 'goodadd'],
	'nonce'     => $accept_add,
	'name_flag' => 'yes',
]);
$add_expected = $saved_meta . ' | wp_update_term(123,"pa_cows",name="Holstein (+$5.00)",desc="A cow.")';
t_assert($add === $add_expected,
	$add === $add_expected ? 'add path saves and annotates' : "add path saves and annotates — got: $add");
t_assert($GLOBALS['mt2mba_test']['nonce_checks'] === [['goodadd', 'mt2mba_add_term']],
	'add path verifies the mt2mba_add_term nonce');
//endregion

//region Storage and annotation matrix
$cases = [
	'name on, desc off' => [
		['name_flag' => 'yes'],
		$saved_meta . ' | wp_update_term(123,"pa_cows",name="Holstein (+$5.00)",desc="A cow.")',
	],
	'name off, desc on' => [
		['desc_flag' => 'yes'],
		$saved_meta . ' | wp_update_term(123,"pa_cows",name="Holstein",desc="A cow.\n(Add $5.00)")',
	],
	// The two fields deliberately disagree since 4.7.0: a currency markup takes the
	// sign form in the NAME and keeps the word form in the DESCRIPTION (Mark's call,
	// 2026-08-13). Pinned so the split reads as a decision, not a missed conversion.
	'name on, desc on' => [
		['name_flag' => 'yes', 'desc_flag' => 'yes'],
		$saved_meta . ' | wp_update_term(123,"pa_cows",name="Holstein (+$5.00)",desc="A cow.\n(Add $5.00)")',
	],
	'both off — meta only, term untouched' => [
		[],
		$saved_meta,
	],
	'negative percentage reads as Subtract' => [
		[
			'post' => ['term_markup' => '-5%', '_wpnonce' => 'goodedit'],
			'name_flag' => 'yes', 'desc_flag' => 'yes',
		],
		'delete_term_meta(123,mt2mba_markup,null) | update_term_meta(123,mt2mba_markup,"-5%")'
			. ' | wp_update_term(123,"pa_cows",name="Holstein (Subtract 5%)",desc="A cow.\n(Subtract 5%)")',
	],
	'garbage markup stores nothing and rewrites nothing' => [
		[
			'post' => ['term_markup' => '5abc', '_wpnonce' => 'goodedit'],
			'name_flag' => 'yes', 'desc_flag' => 'yes',
		],
		'delete_term_meta(123,mt2mba_markup,null)',
	],
	'cleared markup strips the old annotation' => [
		[
			'post' => ['term_markup' => '', '_wpnonce' => 'goodedit'],
			'term' => ['name' => 'Holstein (Add $5.00)', 'desc' => "A cow.\n(Add \$5.00)"],
			'name_flag' => 'yes', 'desc_flag' => 'yes',
		],
		'delete_term_meta(123,mt2mba_markup,null)'
			. ' | wp_update_term(123,"pa_cows",name="Holstein",desc="A cow.")',
	],
	'mixed-case taxonomy is sanitized for both the option lookup and the update' => [
		[
			'term' => ['taxonomy' => 'PA_Cows'],
			'name_flag' => 'yes',
		],
		$saved_meta . ' | wp_update_term(123,"pa_cows",name="Holstein (+$5.00)",desc="A cow.")',
	],
	'padded term name is trimmed even with both flags off' => [
		['term' => ['name' => '  Holstein  ']],
		$saved_meta . ' | wp_update_term(123,"pa_cows",name="Holstein",desc="A cow.")',
	],
];
foreach ($cases as $label => [$case, $expected]) {
	$actual = t19_save($term_component, $case);
	t_assert($actual === $expected,
		$actual === $expected ? $label : "$label\n        expected: $expected\n        actual:   $actual");
}
//endregion

t_done();
