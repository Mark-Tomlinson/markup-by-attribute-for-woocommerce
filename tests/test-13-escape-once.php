<?php
/**
 * Item 10 — escape at output, exactly once.
 *
 * `sanitizeMarkupForDisplay()` was an output escaper (esc_html . sanitize_text_field)
 * wearing a sanitizer's name, and all three of its callers escaped the result
 * again. That never produced visible damage because WordPress escapes with
 * double_encode = FALSE — which is exactly what let it survive so long: the
 * pipeline read as broken and rendered as fine. (The harness stub used to
 * double-encode, which would have invented a bug that cannot happen in
 * production; it now models WordPress.)
 *
 * So most of this file asserts that output is SAFE and escaped once, rather than
 * demonstrating a rendering bug that never existed. The exception is the edit
 * field, which had a real one — see the first region.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/utility/general.php';
require __DIR__ . '/../src/utility/notices.php';
require __DIR__ . '/../src/backend/term.php';

use mt2Tech\MarkupByAttribute\Utility\General;
use mt2Tech\MarkupByAttribute\Utility\Notices;
use mt2Tech\MarkupByAttribute\Backend\Term;

// Term registers its hooks from the constructor, once, for every global
// attribute taxonomy — so this has to be in place before get_instance().
$GLOBALS['mt2mba_stub']['attribute_taxonomies'] = [(object) ['attribute_name' => 'color']];
$term_admin = Term::get_instance();

/** Capture what a form callback echoes. */
function render(callable $fn): string {
	ob_start();
	$fn();
	return ob_get_clean();
}

function make_term(int $term_id, string $name = 'Red'): object {
	$term = new WP_Term();
	$term->term_id  = $term_id;
	$term->name     = $name;
	$term->slug     = 'red';
	$term->taxonomy = 'pa_color';
	return $term;
}

function set_markup(int $term_id, string $markup): void {
	$GLOBALS['mt2mba_stub']['term_meta_in'] = [$term_id => ['mt2mba_markup' => $markup]];
}

//region A markup of "0" must survive to the edit field
// The old expression was `esc_attr($m) ? esc_attr($m) : ''` — it calls the
// escaper twice and then uses its result as a boolean. esc_attr('0') is the
// string '0', which is falsy in PHP, so a stored markup of zero rendered as an
// EMPTY field.
//
// Honest scope: validateMarkupValue('0') returns '' (see test-06), so the normal
// save path cannot store a bare '0' today — this is reachable only through
// legacy rows or a hand-edited database, and it would become reachable again if
// zero were ever treated as a real markup. Kept as a regression guard; it is the
// one assertion in this file that fails against the pre-item-10 code.
set_markup(55, '0');
$html = render(fn() => $term_admin->editTermFields(make_term(55)));
t_assert(
	strpos($html, 'id="term_edit_markup" value="0"') !== false,
	'a stored markup of 0 renders as 0 in the edit field, not blank'
);

set_markup(55, '12.50');
$html = render(fn() => $term_admin->editTermFields(make_term(55)));
t_assert(
	strpos($html, 'id="term_edit_markup" value="12.50"') !== false,
	'an ordinary markup still reaches the edit field'
);

// An absent markup still yields an empty field rather than the string 'false'
$GLOBALS['mt2mba_stub']['term_meta_in'] = [];
$html = render(fn() => $term_admin->editTermFields(make_term(55)));
t_assert(
	strpos($html, 'id="term_edit_markup" value=""') !== false,
	'a term with no markup renders an empty field'
);
//endregion

//region The edit field escapes hostile input once, and safely
// A bare quote that survived would close value=" and open an event handler.
set_markup(55, '5" onmouseover="alert(1)');
$html = render(fn() => $term_admin->editTermFields(make_term(55)));

t_assert(strpos($html, 'onmouseover="alert(1)"') === false, 'a quote in the markup cannot break out of the value attribute');
t_assert(strpos($html, '&quot;') !== false, 'the quote is entity-encoded');
t_assert(strpos($html, '&amp;quot;') === false, 'and encoded exactly once, not twice');
//endregion

//region The markup admin column escapes once
// term.php wrapped sanitizeMarkupForDisplay() — itself an escaper — in another
// esc_html(). An ampersand is the tell: escaped once it is '&amp;'; a second
// pass under WordPress's real no-double-encode rules leaves it alone, so the
// only way to see the difference is to check the value is neither mangled nor
// left raw.
$column_filter = null;
foreach ($GLOBALS['mt2mba_test']['actions']['manage_pa_color_custom_column'] ?? [] as $callback) {
	$column_filter = $callback;
}
t_assert(is_callable($column_filter), 'the markup column filter is registered');

set_markup(55, '5 & 6');
$cell = $column_filter('', 'markup', 55);
t_assert($cell === '5 &amp; 6', "the column escapes the ampersand once (got '$cell')");
t_assert(strpos($cell, '&amp;amp;') === false, 'and does not double-encode it');

// A column other than ours passes through untouched
t_assert($column_filter('original', 'name', 55) === 'original', 'the filter ignores columns that are not ours');
//endregion

//region A dismissed notice renders nothing
// notices.php checked the dismissal option twice: once in notice() before
// queuing, then again inside the admin_notices closure. The second check cannot
// ever differ — handleNoticeDismissal() calls wp_die(), so no single request
// both dismisses a notice and renders one. Removing it must change nothing.
$GLOBALS['mt2mba_test']['options']['mt2mba_dismissed_test_notice'] = true;
$GLOBALS['mt2mba_test']['actions']['admin_notices'] = [];
Notices::get_instance()->sendNoticeArray(['info' => [['test_notice', 'Should never appear']]]);

t_assert(empty($GLOBALS['mt2mba_test']['actions']['admin_notices']), 'a dismissed notice is never queued');
t_assert(render(fn() => do_action('admin_notices')) === '', 'and produces no output');

// An undismissed one still shows
$GLOBALS['mt2mba_test']['options'] = [];
$GLOBALS['mt2mba_test']['actions']['admin_notices'] = [];
Notices::get_instance()->sendNoticeArray(['info' => [['live_notice', 'Should appear']]]);
$output = render(fn() => do_action('admin_notices'));

t_assert(strpos($output, 'Should appear') !== false, 'an undismissed notice still renders');
t_assert(strpos($output, 'mt2mba_dismiss=live_notice') !== false, 'with its dismissal link intact');
//endregion

//region The misnamed escaper is gone and stays gone
t_assert(
	!method_exists(General::class, 'sanitizeMarkupForDisplay'),
	'General::sanitizeMarkupForDisplay() no longer exists (it escaped, despite its name, and every caller escaped again)'
);
//endregion

t_done();
