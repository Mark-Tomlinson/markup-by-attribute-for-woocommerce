<?php
/**
 * Reapply markups bulk action — the redirect must not carry the last run's work
 *
 * The bug (reported by Mark on a live store): select only simple products, apply
 * "Reapply Markups", and the products from the PREVIOUS run are repriced again.
 * Select nothing at all and core's "select at least one item" error does not clear
 * it either; the next simple-product run repeats it once more.
 *
 * The mechanism is in core, and it is the reason a handler that only ADDS its
 * argument is not enough. edit.php builds the redirect from wp_get_referer():
 *
 *     $sendback = remove_query_arg( array('trashed','untrashed','deleted','locked','ids'), wp_get_referer() );
 *
 * wp_get_referer() reads the list table's _wp_http_referer hidden field, and
 * wp_referer_field() filled that field from REQUEST_URI when the page rendered —
 * BEFORE the JS scrubbed reapply_markups_ids out of the address bar. So the last
 * run's IDs are still in the form, core's strip list does not know our argument,
 * and a run with nothing to add hands them straight back.
 *
 * Verified against WP core on WPDev 2026-09-20.
 *
 * Repricing is a write. "Nothing was selected so nothing happened" has to mean
 * nothing happened, even when a markup changed in between.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/backend/productlist.php';

use mt2Tech\MarkupByAttribute\Backend\ProductList;

/** A product that can answer is_type(), which the bulk filter turns on. */
class MT2MBA_T33_Product {
	private $type;
	public function __construct(string $type) { $this->type = $type; }
	public function is_type($type) { return $this->type === $type; }
}

/** Register products as [id => 'variable'|'simple']. */
function t33_products(array $types): void {
	$GLOBALS['mt2mba_stub']['products'] = [];
	foreach ($types as $product_id => $type) {
		$GLOBALS['mt2mba_stub']['products'][$product_id] = new MT2MBA_T33_Product($type);
	}
}

/** The redirect, with the stub's URL-encoding undone so assertions stay readable. */
function t33_redirect(ProductList $list, string $referer, array $selected, string $action = 'reapply_markups'): string {
	return urldecode($list->processBulkActions($referer, $action, $selected));
}

/** Render the product list notices and return the HTML. */
function t33_notice(ProductList $list, string $screen_id = 'edit-product'): string {
	$GLOBALS['mt2mba_stub']['screen_id'] = $screen_id;
	ob_start();
	$list->showBulkActionNotice();
	return ob_get_clean();
}

$list = ProductList::get_instance();

// The referer as the browser really hands it back: still carrying the IDs from the
// run before, because the hidden field predates the address bar being cleaned.
$stale = 'http://test/wp-admin/edit.php?post_type=product&reapply_markups_ids=10,11';
$clean = 'http://test/wp-admin/edit.php?post_type=product';

//region The stale run does not survive a selection with nothing to do
t33_products([10 => 'variable', 11 => 'variable', 20 => 'simple']);

$redirect = t33_redirect($list, $stale, [20]);
t_assert(strpos($redirect, 'reapply_markups_ids') === false,
	'selecting only a simple product drops the previous run\'s IDs');
t_assert(strpos($redirect, 'reapply_markups_none=1') !== false,
	'...and says so instead of redirecting silently');
t_assert(strpos($redirect, 'post_type=product') !== false,
	'...while the rest of the referer survives');

// Core's own "select at least one item" path never reaches this filter, so the
// arguments have to be cleared by the NEXT run rather than by that error.
$redirect = t33_redirect($list, $stale, []);
t_assert(strpos($redirect, 'reapply_markups_ids') === false,
	'an empty selection drops them too');
//endregion

//region A real run is not contaminated by what came before
$redirect = t33_redirect($list, $stale, [10]);
t_assert(strpos($redirect, 'reapply_markups_ids=10') !== false
	&& strpos($redirect, '11') === false,
	'a new run carries only its own products, not the last run\'s');

// The warning is an argument like any other, so it rides the referer as well. Left
// in place, a successful run would print "nothing was selected" over its own progress.
$warned = 'http://test/wp-admin/edit.php?post_type=product&reapply_markups_none=1';
$redirect = t33_redirect($list, $warned, [10]);
t_assert(strpos($redirect, 'reapply_markups_none') === false,
	'a successful run does not inherit the previous run\'s warning');

// Mixed selections are the ordinary case and stay quiet: the simple products are
// skipped, which is what the shopowner already expects of a bulk action.
$redirect = t33_redirect($list, $clean, [10, 20, 11]);
t_assert(strpos($redirect, 'reapply_markups_ids=10,11') !== false,
	'a mixed selection processes the variable products');
t_assert(strpos($redirect, 'reapply_markups_none') === false,
	'...and does not warn, because something was in fact done');

// A product id that no longer resolves must not take the batch down
t33_products([10 => 'variable']);
$redirect = t33_redirect($list, $clean, [10, 999]);
t_assert(strpos($redirect, 'reapply_markups_ids=10') !== false,
	'a deleted product is skipped and the rest of the batch still runs');
//endregion

//region Another bulk action is none of our business
// Returning a scrubbed URL here would strip arguments from Trash, Edit, or any
// third-party action that happened to be mid-flight.
t33_products([10 => 'variable', 20 => 'simple']);
t_assert($list->processBulkActions($stale, 'trash', [20]) === $stale,
	'a foreign bulk action gets its redirect back byte for byte');
//endregion

//region The notice appears exactly where it was sent
$_GET = ['reapply_markups_none' => '1'];
$html = t33_notice($list);
t_assert(strpos($html, 'notice-warning') !== false,
	'the warning renders when the redirect asked for it');
t_assert(strpos($html, 'none were selected') !== false,
	'...and names the reason rather than just reporting a failure');

// A receipt, not a standing condition: it describes one click and has nothing to
// say afterwards, so it fades the way the term list's result notice does.
t_assert(strpos($html, 'mt2mba-notice-transient') !== false,
	'the warning is marked to fade');
$css = file_get_contents(__DIR__ . '/../src/css/admin-style.css');
t_assert(strpos($css, '.mt2mba-notice-transient') !== false,
	'the class the markup asks for is defined in the stylesheet');
t_assert(strpos(file_get_contents(__DIR__ . '/../src/backend/productlist.php'), 'mt2mba-admin-styles') !== false,
	'...and the product list enqueues that stylesheet');

// admin_notices fires on every admin page, and the argument rides any URL it is
// pasted into. Without the screen test the warning would follow the user around.
t_assert(t33_notice($list, 'edit-post') === '', 'another list screen gets nothing');
t_assert(t33_notice($list, 'options-general') === '', 'a settings screen gets nothing');

$_GET = [];
t_assert(t33_notice($list) === '', 'no argument, no notice');
//endregion

//region The address bar is cleaned of BOTH arguments
// PHP has already printed the notice into the page by the time this runs, so the
// warning is dropped along with the IDs. Leave it and a refresh re-warns about a
// click that happened minutes ago; worse, it feeds back into _wp_http_referer.
$js = file_get_contents(__DIR__ . '/../src/js/jq-mt2mba-reapply-markups-productlist.js');

t_assert(preg_match('/urlParams\.delete\(\s*\'reapply_markups_ids\'\s*\)/', $js) === 1,
	'the JS drops reapply_markups_ids from the URL');
t_assert(preg_match('/urlParams\.delete\(\s*\'reapply_markups_none\'\s*\)/', $js) === 1,
	'the JS drops reapply_markups_none as well');
t_assert(preg_match('/history\.replaceState/', $js) === 1,
	'...by rewriting the URL in place rather than navigating');

// The scrub is now reached by two different conditions. A reprice that only ran
// when IDs were present is the whole feature, so it must not have moved inside
// the branch that also fires for the warning.
t_assert(preg_match('/if\s*\(\s*bulkIds\s*\)\s*\{\s*\n\s*processBulkReapply/', $js) === 1,
	'the bulk reprice still runs only when there are IDs to reprice');
//endregion

t_done();
