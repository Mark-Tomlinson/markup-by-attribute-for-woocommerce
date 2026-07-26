<?php
/**
 * Item 3 — Invalid markup handling: user feedback is client-side
 * (jq-mt2mba-validate-markup.js blocks the submit with WP's form-invalid
 * styling), so the server-side handler must simply discard invalid input
 * cleanly: no stored meta, no term rewrite, no transients, no notice hooks,
 * and no PHP errors.
 *
 * History: the old add_action('admin_notices') message was unreachable on
 * both paths (AJAX on add, redirect-after-save on edit) and its "10%"/"-5%"
 * literals were unescaped sprintf specifiers — a fatal on PHP 8 had it ever
 * rendered. The whole branch is gone; this test pins the silent-discard
 * contract and keeps dead notice machinery from creeping back in.
 *
 * The JS itself is browser-tested (add + edit a term with garbage markup;
 * expect red field border and no save).
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

$scenarios = [
	'AJAX add'    => ['doing_ajax' => true,  'post' => ['term_markup' => 'abc', 'mt2mba_term_nonce' => 'testnonce']],
	'normal edit' => ['doing_ajax' => false, 'post' => ['term_markup' => 'xyz', '_wpnonce' => 'testnonce']],
];

foreach ($scenarios as $label => $scenario) {
	$GLOBALS['mt2mba_stub']['doing_ajax'] = $scenario['doing_ajax'];
	$_POST = $scenario['post'];
	$notices_before = count($GLOBALS['mt2mba_test']['actions']['admin_notices'] ?? []);

	try {
		$term_component->handleTermMarkupSave(123);
		t_assert(true, "$label: invalid markup handled without PHP error");
	} catch (Throwable $e) {
		t_assert(false, "$label: invalid markup handled without PHP error (" . get_class($e) . ': ' . $e->getMessage() . ')');
	}

	$notices_added = count($GLOBALS['mt2mba_test']['actions']['admin_notices'] ?? []) - $notices_before;
	t_assert($notices_added === 0, "$label: no admin_notices hook registered during save (dead on both paths)");
	t_assert(empty($GLOBALS['mt2mba_test']['transients']), "$label: no transient stored");

	$meta_updates = array_filter($GLOBALS['mt2mba_test']['term_meta'], fn($m) => $m[0] === 'update');
	t_assert(empty($meta_updates), "$label: invalid markup not written to term meta");
	t_assert(empty($GLOBALS['mt2mba_test']['term_updates']), "$label: term name/description untouched");
}

t_done();
