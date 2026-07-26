<?php
/**
 * Item 5 — handleTermMarkupSave() must survive get_term() returning
 * null or WP_Error (term deleted in a race, plugin conflict) instead of
 * dereferencing $term->taxonomy on a non-WP_Term.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/utility/general.php';
require __DIR__ . '/../src/backend/term.php';

use mt2Tech\MarkupByAttribute\Backend\Term;


$term_component = Term::get_instance();
$_POST = ['term_markup' => '5.00', '_wpnonce' => 'testnonce'];

// get_term() returns null
$GLOBALS['mt2mba_stub']['get_term'] = fn($term_id) => null;
try {
	$term_component->handleTermMarkupSave(123);
	t_assert(true, 'null term: handler returns without error');
} catch (Throwable $e) {
	t_assert(false, 'null term: handler returns without error (' . get_class($e) . ': ' . $e->getMessage() . ')');
}

// get_term() returns WP_Error
$GLOBALS['mt2mba_stub']['get_term'] = fn($term_id) => new WP_Error();
try {
	$term_component->handleTermMarkupSave(123);
	t_assert(true, 'WP_Error term: handler returns without error');
} catch (Throwable $e) {
	t_assert(false, 'WP_Error term: handler returns without error (' . get_class($e) . ': ' . $e->getMessage() . ')');
}

t_assert(empty($GLOBALS['mt2mba_test']['term_meta']), 'no term meta touched when the term cannot be fetched');
t_assert(empty($GLOBALS['mt2mba_test']['term_updates']), 'no wp_update_term when the term cannot be fetched');

t_done();
