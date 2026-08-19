<?php
/**
 * Item 19 — the pointer script must carry its own version, and load in the footer
 *
 * wp_enqueue_script() was called with no $ver, so WordPress stamped the script
 * with the WP core version instead. Cache-busting then depended on WordPress
 * shipping a release: a user updating only this plugin could keep running the
 * old pointer JS out of browser cache indefinitely.
 *
 * This is invisible in a browser until it bites someone, which is exactly why it
 * gets a test rather than a manual look.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/utility/pointers.php';

use mt2Tech\MarkupByAttribute\Utility\Pointers;

// The constructor registers the pointer filters this screen resolves to
$pointers = Pointers::get_instance();

/** Run the enqueue hook for one screen and return what it enqueued. */
$load = function ($screen_id, $dismissed = '') use ($pointers) {
	$GLOBALS['mt2mba_test']['enqueued'] = [];
	$GLOBALS['mt2mba_stub']['screen_id'] = $screen_id;
	$GLOBALS['mt2mba_stub']['user_meta'] = ['dismissed_wp_pointers' => $dismissed];

	$pointers->adminPointerLoad('irrelevant');

	return $GLOBALS['mt2mba_test']['enqueued'];
};

//region The term screen, where the pointers actually live
$enqueued = $load('edit-pa_color');
$script = $enqueued['script']['mt2mba-pointer'] ?? null;

t_assert($script !== null, 'term screen enqueues the mt2mba-pointer script');
t_assert(($script['ver'] ?? null) === MT2MBA_VERSION,
	'pointer script is versioned with MT2MBA_VERSION (got: ' . var_export($script['ver'] ?? null, true) . ')');
t_assert(($script['in_footer'] ?? null) === true,
	'pointer script loads in the footer (got: ' . var_export($script['in_footer'] ?? null, true) . ')');
t_assert(($script['deps'] ?? []) === array('wp-pointer'),
	'pointer script still depends on wp-pointer, so core loads first');
t_assert(isset($enqueued['style']['wp-pointer']),
	'pointer styles are enqueued alongside');
//endregion

//region Screens with nothing to say, and pointers already dismissed
$none = $load('dashboard');
t_assert($none === [], 'a screen with no pointers enqueues nothing');

$dismissed = $load('edit-pa_color', 'mt2mba-term_add_markup,mt2mba-term_edit_markup');
t_assert($dismissed === [], 'every pointer dismissed enqueues nothing');
//endregion

t_done();
