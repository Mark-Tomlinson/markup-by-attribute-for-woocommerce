<?php
/**
 * Item 25 — a failed reapply on the product edit page must not be silent.
 *
 * Found while fixing item 24. Three of the four $.ajax calls in the product JS were
 * success:-only, and every one of them wrapped its body in `if (response.success)`
 * with no `else`. That is TWO silent paths per call, not one:
 *
 *   1. the request itself failing (timeout, 500, expired session)     -> no error:
 *   2. the server reporting failure via wp_send_json_error()          -> no else
 *
 * handleMarkupReapplication() catches Throwable and answers with wp_send_json_error,
 * and validateReapplyMarkupsRequest() answers "Permission denied" the same way, so
 * path 2 is not hypothetical — it is the ordinary way this handler says no. Either
 * way the user got silence: no message, no console note, and a variations panel that
 * simply never refreshed. Meanwhile 'failedRecalculating' sat localized into ten
 * locales, referenced by nothing, BECAUSE the error path was never finished.
 *
 * The sibling file jq-mt2mba-reapply-markups-productlist.js already handles both
 * paths and funnels them into one showReapplyFailure() (item 22 / item 19). This is
 * the same shape applied to the file that item 22 never touched.
 *
 * ⚠️ WHAT THIS TEST DOES NOT DO. Like test-24, it cannot execute the fix — that needs
 * a browser with devtools throttling to make the AJAX actually fail. This guards the
 * wiring, which is the part that can silently regress.
 *
 * THE MESSAGE DISTINCTION IS THE POINT OF THE LAST REGION. The third call
 * (woocommerce_load_variations) fires AFTER the reprice has already committed. Telling
 * the user "Failed to reapply markups" there would be false on the money path — the
 * prices DID change. It gets its own string.
 */
require __DIR__ . '/bootstrap.php';

$js  = file_get_contents(__DIR__ . '/../src/js/jq-mt2mba-reapply-markups-product.js');
$php = file_get_contents(__DIR__ . '/../src/backend/product.php');

//region There is one shared failure helper, and it does not stack notices
t_assert(preg_match('/function\s+showReapplyFailure\s*\(/', $js) === 1,
	'a named showReapplyFailure() helper exists, mirroring the productlist file');

// Native WP admin notice classes so this needs no CSS of its own.
t_assert(strpos($js, 'notice-error') !== false,
	'the failure surfaces as a standard WordPress notice-error');

t_assert(strpos($js, 'mt2mba-reapply-error') !== false,
	'the notice carries our own class so it can be found again');

// A user who retries three times should see one notice, not three. The helper has to
// clear the previous one before inserting; .remove() on our own class is how.
t_assert(preg_match('/mt2mba-reapply-error[\'"]?\s*\)?\s*\.remove\(\)/', $js) === 1
	|| preg_match('/\$\(\s*[\'"]\.mt2mba-reapply-error[\'"]\s*\)\.remove\(\)/', $js) === 1,
	'an existing notice is removed before a new one is inserted (no stacking)');

// It belongs in the variations panel, which is also what makes it self-clearing:
// [Save attributes] replaces #variable_product_options wholesale (see test-24).
t_assert(strpos($js, '#variable_product_options') !== false,
	'the notice is placed in the variations panel');
//endregion

//region Every AJAX call in the file has an error: handler
// Structural count rather than per-call inspection: nested callbacks make a positional
// regex unreadable, and the invariant we actually care about is "none left behind".
$ajax_calls    = preg_match_all('/\$\.ajax\(\s*\{/', $js);
$error_handlers = preg_match_all('/\berror:\s*function/', $js);

t_assert($ajax_calls === 4,
	"the file still makes 4 AJAX calls (found $ajax_calls) — guards the count below");

t_assert($error_handlers === $ajax_calls,
	"every AJAX call has an error: handler ($error_handlers of $ajax_calls)");
//endregion

//region The server-said-no path is handled too, not just the transport path
// Before the fix this file contained no `else` at all: wp_send_json_error() landed in
// success: with response.success false, fell past the if, and vanished.
$failure_uses = preg_match_all('/showReapplyFailure\s*\(/', $js);

// 1 definition + 3 error: handlers + 2 else branches on the JSON-returning calls.
// (woocommerce_load_variations returns raw HTML, so it has no success flag to test.)
t_assert($failure_uses >= 6,
	"showReapplyFailure is wired into both failure paths of each call (found $failure_uses of 6+)");

t_assert(preg_match_all('/\}\s*else\s*\{/', $js) >= 2,
	'both JSON-returning calls have an else branch for response.success === false');
//endregion

//region The dead string is alive, and the post-commit call does not lie
t_assert(strpos($js, 'mt2mbaLocal.i18n.failedRecalculating') !== false,
	'failedRecalculating is finally read by the JS it was written for');

t_assert(strpos($php, "'failedRefreshing'") !== false,
	'product.php defines the separate post-commit message');

$localized_block = t_array_block($php, 'i18n');
t_assert($localized_block !== null && strpos($localized_block, "'failedRefreshing'") !== false,
	'failedRefreshing is localized inside the i18n block');

t_assert(strpos($js, 'mt2mbaLocal.i18n.failedRefreshing') !== false,
	'the JS reads failedRefreshing');

// THE important one. The panel-reload call runs after the reprice has committed, so
// "Failed to reapply markups" would be a lie there. Walk to that call's OWN error
// handler rather than reading to end-of-file -- the two enclosing calls use
// failedRecalculating perfectly correctly, and a looser scope catches them instead.
$reload_at = strpos($js, "'woocommerce_load_variations'");
t_assert($reload_at !== false, 'the panel-reload call is still present');

$err_at  = strpos($js, 'error:', $reload_at);
$call_at = $err_at === false ? false : strpos($js, 'showReapplyFailure(', $err_at);
$message = $call_at === false ? '' : substr($js, $call_at, 80);

t_assert(strpos($message, 'failedRefreshing') !== false,
	'the panel-reload error handler says the REFRESH failed');

t_assert(strpos($message, 'failedRecalculating') === false,
	'the panel-reload error handler does NOT claim the reprice failed — it succeeded');
//endregion

t_done();
