<?php
/**
 * Item 14 — handleMarkupColumnSort() is hooked to pre_get_terms, which fires on
 * frontend term queries too. Any request carrying ?orderby=markup could rewrite
 * a frontend query's vars. The handler must do nothing outside the admin.
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/utility/general.php';
require __DIR__ . '/../src/backend/term.php';

use mt2Tech\MarkupByAttribute\Backend\Term;

$term_component = Term::get_instance();

/** A stand-in for WP_Term_Query: only the two members the handler touches. */
function t_term_query(): object {
	$q = new stdClass();
	$q->query_vars = ['orderby' => 'name'];
	return $q;
}

// --- Frontend request: the guard must bail before anything is rewritten -----
$GLOBALS['mt2mba_stub']['is_admin'] = false;
$_GET = ['orderby' => 'markup'];

$query = t_term_query();
$term_component->handleMarkupColumnSort($query);

t_assert($query->query_vars['orderby'] === 'name',
	'frontend: orderby left alone');
t_assert(!isset($query->meta_query),
	'frontend: no meta_query bolted onto the query');

// --- Admin request: unchanged behavior -------------------------------------
$GLOBALS['mt2mba_stub']['is_admin'] = true;

$query = t_term_query();
$term_component->handleMarkupColumnSort($query);

t_assert($query->query_vars['orderby'] === 'mt2mba_markup',
	'admin: orderby rewritten to the markup meta key');
t_assert(isset($query->meta_query) && $query->meta_query instanceof WP_Meta_Query,
	'admin: meta_query attached');

// --- Admin request without the sort param: still a no-op -------------------
$_GET = [];

$query = t_term_query();
$term_component->handleMarkupColumnSort($query);

t_assert($query->query_vars['orderby'] === 'name',
	'admin, no orderby param: query untouched');

t_done();
