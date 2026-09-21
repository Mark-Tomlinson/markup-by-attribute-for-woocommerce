<?php
/**
 * A stored markup reads the same however old the term is.
 *
 * Older releases stored the leading '+' and the trailing zeros a user typed, so
 * a term untouched for years holds '+1.00' while one saved today holds '1'. Both
 * are the same markup, but the two admin fields that show the value unformatted
 * — the attribute list's Markup column and the term edit field — showed the
 * spelling, and the sortable column ordered by it as text: '+8' landed above '1'
 * and '10' above '2'.
 *
 * The stored values are deliberately left alone. Both halves of the fix are read
 * side: canonicalize on display, and cast on sort.
 *
 * Confirms MySQL's own ordering, measured on the CAST this test pins:
 *   text          ASC: -10  -5  +1.00  +8  1  10  2  8%
 *   DECIMAL(10,4) ASC: -10  -5  1  +1.00  2  +8  8%  10
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/utility/general.php';
require __DIR__ . '/../src/backend/term.php';

use mt2Tech\MarkupByAttribute\Utility\General;
use mt2Tech\MarkupByAttribute\Backend\Term;

//region Every past spelling collapses onto one display form
// Each case is [stored, dot store, comma store].
$cases = [
	['+1.00',      '1',       '1'],
	['+1',         '1',       '1'],
	['1.00',       '1',       '1'],
	['1',          '1',       '1'],
	['+8',         '8',       '8'],
	['+0.50',      '0.5',     '0,5'],
	['-5',         '-5',      '-5'],
	['-5.50',      '-5.5',    '-5,5'],
	['+3.14%',     '3.14%',   '3,14%'],
	['3.140000%',  '3.14%',   '3,14%'],
	['-2.5%',      '-2.5%',   '-2,5%'],
	['1234.5',     '1234.5',  '1234,5'],
	['',           '',        ''],
	['   ',        '',        ''],
];

foreach ([['.', 1], [',', 2]] as [$separator, $column]) {
	$GLOBALS['mt2mba_stub']['decimal_separator'] = $separator;
	foreach ($cases as $case) {
		$actual = General::formatStoredMarkupForDisplay($case[0]);
		t_assert($actual === $case[$column],
			sprintf("display %-12s [%s] -> %-8s", "'" . $case[0] . "'", $separator, "'" . $actual . "'"));
	}
}
//endregion

//region The comma-decimal trap
// normalizeMarkupNotation() strips a leading '+' too, but it reads the STORE'S
// separator: reusing it here would take the '.' in '+1.00' for a thousands
// separator and display 100. Same class of bug as feeding a stored value back
// through the input path.
$GLOBALS['mt2mba_stub']['decimal_separator'] = ',';
t_assert(General::formatStoredMarkupForDisplay('+1.00') === '1',
	"comma store: '+1.00' displays as 1, not 100");
t_assert(General::normalizeMarkupNotation('+1.00', ',') === '100',
	'comma store: normalizeMarkupNotation() would indeed return 100 (why it is not reused)');
$GLOBALS['mt2mba_stub']['decimal_separator'] = '.';
//endregion

//region Idempotent — a displayed value re-displays unchanged
foreach ($cases as $case) {
	$once = General::formatStoredMarkupForDisplay($case[0]);
	t_assert(General::formatStoredMarkupForDisplay($once) === $once,
		sprintf("idempotent %-12s -> %-8s stays put", "'" . $case[0] . "'", "'" . $once . "'"));
}
//endregion

//region Both display sites actually run it
$GLOBALS['mt2mba_stub']['attribute_taxonomies'] = [(object) ['attribute_name' => 'year']];
$term_component = Term::get_instance();

// The Markup column, driven through the filter the plugin registered
$GLOBALS['mt2mba_stub']['term_meta_in'][42]['mt2mba_markup'] = '+1.00';
$column = $GLOBALS['mt2mba_test']['actions']['manage_pa_year_custom_column'][0] ?? null;
t_assert(is_callable($column), 'Markup column filter registered');
t_assert($column('', 'markup', 42) === '1',
	"column: stored '+1.00' renders as 1");

// Other plugins' columns must still pass through untouched
t_assert($column('someone-elses-content', 'not_markup', 42) === 'someone-elses-content',
	'column: a different column is left alone');

// The term edit field
$GLOBALS['mt2mba_stub']['get_term'] = null;
$term = new WP_Term();
$term->term_id = 42;
ob_start();
$term_component->editTermFields($term);
$field = ob_get_clean();
t_assert(strpos($field, 'value="1"') !== false,
	"edit field: stored '+1.00' renders as value=\"1\"");
t_assert(strpos($field, '+1.00') === false,
	'edit field: the stored spelling does not reach the browser');
//endregion

//region The sort casts, and does not depend on clause order
$GLOBALS['mt2mba_stub']['is_admin'] = true;
$_GET = ['orderby' => 'markup'];

$query = new stdClass();
$query->query_vars = ['orderby' => 'name'];
$term_component->handleMarkupColumnSort($query);

$clauses = array_values(array_filter($query->meta_query->queries, 'is_array'));
t_assert(count($clauses) === 2, 'sort: both meta clauses present');

// WP_Term_Query casts using reset($meta_clauses) — the FIRST clause. Typing only
// one leaves the sort one reordering away from silently going back to text.
foreach ($clauses as $i => $clause) {
	t_assert(($clause['type'] ?? '') === 'DECIMAL(10,4)',
		sprintf('sort: clause %d casts to DECIMAL(10,4)', $i));
}
//endregion

t_done();
