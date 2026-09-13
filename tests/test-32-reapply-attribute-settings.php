<?php
/**
 * Reapply Attribute Settings — the term-list bulk action and its notices
 *
 * Two things are worth guarding here. The bulk action changes terms, so it needs
 * the same capability and scope discipline as the save path. And the notice is a
 * standing CONDITION rather than a report of an event: it recomputes on every view
 * of the term list, which means a comparison that is wrong in the cosmetic
 * direction produces a warning that never clears no matter what the shopowner does.
 *
 * The name and the description are counted separately and never combined. That is
 * the design, not an accident, so the fixture deliberately puts the two fields in
 * disagreeing states at the same time.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/utility/general.php';
require __DIR__ . '/../src/backend/term.php';

use mt2Tech\MarkupByAttribute\Backend\Term;

$term_component = Term::get_instance();

/**
 * Build a term the way get_terms() hands one back.
 *
 * The markup is staged rather than written to the meta stub directly: these calls
 * are arguments to t32_fixture(), so PHP runs them BEFORE the fixture clears the
 * previous test's meta, and a direct write would be erased by the very call it was
 * written for.
 */
function t32_term(int $term_id, string $name, string $description, string $markup = ''): WP_Term {
	$term = new WP_Term();
	$term->term_id     = $term_id;
	$term->name        = $name;
	$term->description = $description;
	$term->taxonomy    = 'pa_size';
	$GLOBALS['t32_staged_markup'][$term_id] = $markup;
	return $term;
}

/**
 * Load a taxonomy of terms and set the two attribute flags.
 *
 * Wires get_term() to the same rows get_terms() returns, so the bulk action and
 * the notice are looking at one fixture rather than two that could drift.
 */
function t32_fixture(array $terms, string $name_flag, string $desc_flag): void {
	$GLOBALS['mt2mba_stub']['term_meta_in'] = [];
	$GLOBALS['mt2mba_test']['term_updates'] = [];

	$by_id = [];
	foreach ($terms as $term) {
		$by_id[$term->term_id] = $term;

		$markup = $GLOBALS['t32_staged_markup'][$term->term_id] ?? '';
		if ($markup !== '') {
			$GLOBALS['mt2mba_stub']['term_meta_in'][$term->term_id]['mt2mba_markup'] = $markup;
		}
	}
	$GLOBALS['t32_staged_markup'] = [];

	$GLOBALS['mt2mba_stub']['terms']['pa_size'] = array_values($by_id);
	$GLOBALS['mt2mba_stub']['get_term'] = function ($term_id) use (&$by_id) {
		return $by_id[$term_id] ?? null;
	};
	$GLOBALS['mt2mba_stub']['taxonomy_ids'] = ['pa_size' => 42];
	$GLOBALS['mt2mba_test']['options'] = [
		MT2MBA_REWRITE_TERM_NAME_PREFIX . '42' => $name_flag,
		MT2MBA_REWRITE_TERM_DESC_PREFIX . '42' => $desc_flag,
	];
}

/** Render the notices for the term list screen and return the HTML. */
function t32_notices(Term $term_component, string $taxonomy = 'pa_size'): string {
	$_GET['taxonomy'] = $taxonomy;
	$GLOBALS['mt2mba_stub']['screen_id'] = "edit-{$taxonomy}";
	ob_start();
	$term_component->showTermNotices();
	return ob_get_clean();
}

/** Reflectively reach the private counter. */
function t32_counts(Term $term_component, string $taxonomy = 'pa_size'): array {
	$method = new ReflectionMethod(Term::class, 'countOutOfStepTerms');
	$method->setAccessible(true);
	return $method->invoke($term_component, $taxonomy);
}

//region The action is registered where WordPress will find it
$source = file_get_contents(__DIR__ . '/../src/backend/term.php');

// The screen id behind edit-tags.php is 'edit-{taxonomy}'. Building either hook
// name any other way registers a filter nothing ever calls — and the failure is
// silent, just a menu entry that is not there.
t_assert(strpos($source, 'bulk_actions-edit-{$taxonomy}') !== false,
	'the dropdown entry hooks bulk_actions-edit-{taxonomy}');
t_assert(strpos($source, 'handle_bulk_actions-edit-{$taxonomy}') !== false,
	'the handler hooks handle_bulk_actions-edit-{taxonomy}');

// Reusing the product list's action name would claim this rewrites prices
t_assert(strpos($source, "'Reapply Attribute Settings'") !== false,
	'the action is named Reapply Attribute Settings');
t_assert(strpos($source, "'reapply_markups'") === false,
	"the product list's action name is not reused here");

$menu = $term_component->addTermBulkAction(['delete' => 'Delete']);
t_assert(isset($menu['delete']), 'core\'s Delete action survives');
t_assert(($menu['mt2mba_reapply_settings'] ?? '') === 'Reapply Attribute Settings',
	'our action is added under its own key');
//endregion

//region The handler only acts when it should
t32_fixture([t32_term(1, 'Small', '', '5%')], 'yes', 'no');

$GLOBALS['mt2mba_stub']['can'] = true;
$untouched = $term_component->handleTermBulkAction('http://test/edit-tags.php', 'delete', [1]);
t_assert($untouched === 'http://test/edit-tags.php', 'a different bulk action is passed through unchanged');
t_assert($GLOBALS['mt2mba_test']['term_updates'] === [], 'a different bulk action writes nothing');

$GLOBALS['mt2mba_stub']['can'] = false;
$term_component->handleTermBulkAction('http://test/edit-tags.php', 'mt2mba_reapply_settings', [1]);
t_assert($GLOBALS['mt2mba_test']['term_updates'] === [],
	'a user without manage_product_terms writes nothing');
$GLOBALS['mt2mba_stub']['can'] = true;

// A term deleted between the click and the redirect must not take the batch down
t32_fixture([t32_term(1, 'Small', '', '5%')], 'yes', 'no');
$term_component->handleTermBulkAction('http://test/edit-tags.php', 'mt2mba_reapply_settings', [1, 999]);
t_assert(count($GLOBALS['mt2mba_test']['term_updates']) === 1,
	'a missing term is skipped and the rest of the batch still runs');
//endregion

//region What the bulk action writes
// Flag on: the annotation is added. Flag off: it is removed. Same code path,
// same fixture, opposite settings — this is the whole feature in two assertions.
t32_fixture([t32_term(1, 'Small', 'A size.', '5%')], 'yes', 'no');
$term_component->handleTermBulkAction('http://test/x', 'mt2mba_reapply_settings', [1]);
t_assert(($GLOBALS['mt2mba_test']['term_updates'][0][2]['name'] ?? '') === 'Small (Add 5%)',
	'name flag on — the annotation is applied');

t32_fixture([t32_term(1, 'Small (Add 5%)', 'A size.', '5%')], 'no', 'no');
$term_component->handleTermBulkAction('http://test/x', 'mt2mba_reapply_settings', [1]);
t_assert(($GLOBALS['mt2mba_test']['term_updates'][0][2]['name'] ?? '') === 'Small',
	'name flag off — the annotation is removed');

// A term carrying no markup has nothing to add, but a leftover annotation still goes
t32_fixture([t32_term(1, 'Small (Add 5%)', 'A size.')], 'yes', 'no');
$term_component->handleTermBulkAction('http://test/x', 'mt2mba_reapply_settings', [1]);
t_assert(($GLOBALS['mt2mba_test']['term_updates'][0][2]['name'] ?? '') === 'Small',
	'a term without a markup has its stale annotation removed even with the flag on');

// Only the checked terms: get_terms() treats an empty 'include' as "everything",
// and the handler must never be the code that discovers that
t32_fixture([
	t32_term(1, 'Small', 'A size.', '5%'),
	t32_term(2, 'Large', 'A size.', '10%'),
], 'yes', 'no');
$term_component->handleTermBulkAction('http://test/x', 'mt2mba_reapply_settings', [2]);
$touched = array_column($GLOBALS['mt2mba_test']['term_updates'], 0);
t_assert($touched === [2], 'only the selected term is rewritten');

// Idempotence: the second pass has nothing left to do
t32_fixture([t32_term(1, 'Small (Add 5%)', 'A size.', '5%')], 'yes', 'no');
$term_component->handleTermBulkAction('http://test/x', 'mt2mba_reapply_settings', [1]);
t_assert($GLOBALS['mt2mba_test']['term_updates'] === [],
	'a term already in step is not written at all');

// The count travels back in the URL, and counts writes rather than selections
t32_fixture([
	t32_term(1, 'Small', 'A size.', '5%'),
	t32_term(2, 'Large (Add 10%)', 'A size.', '10%'),
], 'yes', 'no');
$redirect = $term_component->handleTermBulkAction('http://test/x', 'mt2mba_reapply_settings', [1, 2]);
t_assert(strpos($redirect, 'mt2mba_rewritten=1') !== false,
	'the redirect reports 1 rewritten, not 2 selected');

// edit-tags.php seeds $location as FALSE and falls back to the referer only after
// this filter returns, so anything truthy we hand back becomes the redirect whole.
// Returning just '?mt2mba_rewritten=2' lands on an edit-tags.php with no taxonomy
// on it, which is the Tags screen — reproduced on WPDev and BackRev both.
$_SERVER['REQUEST_URI'] = '/wp-admin/edit-tags.php?taxonomy=pa_size&post_type=product&_wpnonce=abc&_wp_http_referer=%2Fx';
t32_fixture([t32_term(1, 'Small', 'A size.', '5%')], 'yes', 'no');
$redirect = $term_component->handleTermBulkAction(false, 'mt2mba_reapply_settings', [1]);
t_assert(strpos($redirect, 'taxonomy=pa_size') !== false,
	'a false $location is rebuilt from the referer, keeping the taxonomy');
t_assert(strpos($redirect, 'post_type=product') !== false,
	'...and post_type, without which the screen renders unstyled');
t_assert(strpos($redirect, '_wpnonce') === false && strpos($redirect, '_wp_http_referer') === false,
	'the spent nonce and referer are dropped from the redirect');
t_assert(strpos($redirect, 'mt2mba_rewritten=1') !== false,
	'the count still rides along');

// A truthy $location from core is built on, not replaced
$redirect = $term_component->handleTermBulkAction(
	'http://test/wp-admin/edit-tags.php?taxonomy=pa_size', 'mt2mba_reapply_settings', []);
t_assert(strpos($redirect, 'taxonomy=pa_size') !== false && strpos($redirect, 'mt2mba_rewritten=0') !== false,
	"core's own location is preserved and added to");

// A bulk action that is not ours must leave false alone, or core's fallback dies
t_assert($term_component->handleTermBulkAction(false, 'delete', [1]) === false,
	'a foreign action gets false back untouched');
//endregion

//region Nothing here touches a product
t32_fixture([t32_term(1, 'Small', 'A size.', '5%')], 'yes', 'yes');
$GLOBALS['mt2mba_test']['post_meta'] = [];
$term_component->handleTermBulkAction('http://test/x', 'mt2mba_reapply_settings', [1]);
t_assert($GLOBALS['mt2mba_test']['post_meta'] === [],
	'the scope fence: reapplying settings writes no product meta');

// wp_update_term() is passed name and description and nothing else. A slug sent
// here would be regenerated from the annotated name, silently breaking every
// variation that references the old one.
t32_fixture([t32_term(1, 'Small', 'A size.', '5%')], 'yes', 'no');
$term_component->handleTermBulkAction('http://test/x', 'mt2mba_reapply_settings', [1]);
$written = array_keys($GLOBALS['mt2mba_test']['term_updates'][0][2]);
sort($written);
t_assert($written === ['description', 'name'], 'the write carries no slug');
//endregion

//region The two fields are counted independently
// Name says one thing, description says the other, at the same time. If either
// count were derived from the other this is where it shows.
t32_fixture([
	t32_term(1, 'Small', "A size.\n(Add 5%)", '5%'),
	t32_term(2, 'Large', "A size.\n(Add 10%)", '10%'),
	t32_term(3, 'Medium (Add 7%)', 'A size.', '7%'),
], 'yes', 'no');
$counts = t32_counts($term_component);
t_assert($counts['name'] === 2, 'two names lack the annotation the flag calls for');
t_assert($counts['description'] === 2, 'two descriptions carry one the flag forbids');

$html = t32_notices($term_component);
t_assert(substr_count($html, 'notice-warning') === 2, 'both fields speak, in two separate notices');
// The state is bolded because it is the word that disambiguates the message, and
// the one a shopowner running two sites can lose track of
t_assert(strpos($html, 'is <strong>on</strong>, but 2 terms\' names do not match their markup') !== false,
	'the name notice reads in the on direction, with the state bolded');
t_assert(strpos($html, 'is <strong>off</strong>, but 2 terms still show a markup in their descriptions') !== false,
	'the description notice reads in the off direction, with the state bolded');

// Emphasis is allowed through on purpose; anything else is not
t_assert(strpos($source, "wp_kses(\$message, ['strong' => []])") !== false,
	'the notice permits <strong> and nothing else');

// Silence is the normal state
t32_fixture([t32_term(1, 'Small (Add 5%)', 'A size.', '5%')], 'yes', 'no');
t_assert(t32_notices($term_component) === '', 'terms in step produce no notice at all');

// One field out of step speaks alone
t32_fixture([t32_term(1, 'Small', 'A size.', '5%')], 'yes', 'no');
$html = t32_notices($term_component);
t_assert(substr_count($html, 'notice-warning') === 1, 'only the disagreeing field speaks');
t_assert(strpos($html, 'Add Markup to Description?') === false,
	'the quiet field is not mentioned');

// Singular is a different string, not "1 terms"
t_assert(strpos($html, "1 term's name does not match its markup") !== false,
	'a single term gets the singular phrasing');
//endregion

//region Cosmetic differences must not raise a warning that can never clear
// Stored term text keeps HTML entities and CRLF endings; stripMarkupAnnotation()
// decodes and trims. A correctly annotated term would otherwise read as out of
// step forever, because viewing the list is not something that can fix it.
t32_fixture([t32_term(1, 'Black &amp; White (Add 5%)', 'A colour.', '5%')], 'yes', 'no');
t_assert(t32_counts($term_component)['name'] === 0,
	'an entity in the stored name is not a mismatch');

t32_fixture([t32_term(1, 'Small', "A size.\r\n(Add 5%)", '5%')], 'no', 'yes');
t_assert(t32_counts($term_component)['description'] === 0,
	'CRLF line endings in the stored description are not a mismatch');

// ...but the bulk action still tidies them, so the count is a floor, not a total
t32_fixture([t32_term(1, 'Small', "A size.\r\n(Add 5%)", '5%')], 'no', 'yes');
$term_component->handleTermBulkAction('http://test/x', 'mt2mba_reapply_settings', [1]);
t_assert(count($GLOBALS['mt2mba_test']['term_updates']) === 1,
	'the bulk action still normalizes what the notice stays quiet about');
//endregion

//region The notices belong to the attribute term list and nowhere else
t32_fixture([t32_term(1, 'Small', 'A size.', '5%')], 'yes', 'no');

$GLOBALS['mt2mba_stub']['screen_id'] = 'edit-post_tag';
$_GET['taxonomy'] = 'post_tag';
ob_start();
$term_component->showTermNotices();
t_assert(ob_get_clean() === '', 'a non-attribute taxonomy gets no notice');

// The screen must agree with the taxonomy, or any admin page carrying
// ?taxonomy=pa_size in its URL would render the warning
$GLOBALS['mt2mba_stub']['screen_id'] = 'edit-product';
$_GET['taxonomy'] = 'pa_size';
ob_start();
$term_component->showTermNotices();
t_assert(ob_get_clean() === '', 'the right taxonomy on the wrong screen gets no notice');
//endregion

//region The result notice
t32_fixture([t32_term(1, 'Small (Add 5%)', 'A size.', '5%')], 'yes', 'no');
$_GET['mt2mba_rewritten'] = '3';
$html = t32_notices($term_component);
t_assert(strpos($html, 'notice-success') !== false && strpos($html, '3 terms updated.') !== false,
	'the bulk action reports how many terms it changed');

// The result fades; the warnings do not. A standing condition that faded would
// be worse than no warning, because it would look like it had been dealt with.
t_assert(strpos($html, 'notice-success mt2mba-notice-transient') !== false,
	'the result notice is marked to fade');
t_assert(strpos($html, 'notice-warning mt2mba-notice-transient') === false,
	'the out-of-step warnings stay put');

$css = file_get_contents(__DIR__ . '/../src/css/admin-style.css');
t_assert(strpos($css, '.mt2mba-notice-transient') !== false && strpos($css, '@keyframes mt2mba-notice-dismiss') !== false,
	'the class the markup asks for is actually defined');

// That stylesheet reaches the term list only through enqueueMarkupValidation(),
// which exists for the markup field. Nothing warns if that guard narrows.
t_assert(strpos($source, "\$hook !== 'edit-tags.php'") !== false && strpos($source, 'mt2mba-admin-styles') !== false,
	'the term screens still enqueue the stylesheet that carries the animation');

$_GET['mt2mba_rewritten'] = '1';
t_assert(strpos(t32_notices($term_component), '1 term updated.') !== false,
	'one term updated reads in the singular');

// Nothing rewritten is still worth saying: it means "I looked and there was
// nothing to do", which is a different message from no notice at all
$_GET['mt2mba_rewritten'] = '0';
t_assert(strpos(t32_notices($term_component), '0 terms updated.') !== false,
	'a run that changed nothing still reports');
unset($_GET['mt2mba_rewritten']);
//endregion

t_done();
