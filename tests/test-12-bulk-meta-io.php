<?php
/**
 * Item 8 — BulkMetaIO in isolation.
 *
 * test-10 proves the extraction did not change what the handlers emit. This
 * file covers the class on its own terms: the empty-input guard that item 2
 * used to require at every call site, and the two places where the SQL shape
 * depends on the input (one meta key vs several, clear-list vs write-list).
 */
require __DIR__ . '/bootstrap.php';
require __DIR__ . '/../src/backend/handlers/bulkmetaio.php';

use mt2Tech\MarkupByAttribute\Backend\Handlers\BulkMetaIO;

function reset_wpdb(): void {
	$GLOBALS['wpdb'] = new MT2MBA_Fake_WPDB();
	$GLOBALS['mt2mba_stub']['wpdb_results'] = [];
}

/** Normalized statements issued since the last reset. */
function sql(): array {
	return array_map(fn($q) => preg_replace('/\s+/', ' ', trim($q)), $GLOBALS['wpdb']->queries);
}

//region The empty-input guard — item 2's fix, now in one place
// Every method must be a no-op on empty input. This is the whole reason the
// class exists: a future call site cannot forget the guard because it is not
// the call site's job any more.
reset_wpdb();
$reads = [
	'fetchMeta'     => BulkMetaIO::fetchMeta([], '_variation_description'),
	'fetchMetaLike' => BulkMetaIO::fetchMetaLike([], 'attribute_pa_%'),
];
BulkMetaIO::deleteMeta([], ['_price']);
BulkMetaIO::deleteMeta([1, 2], []);
BulkMetaIO::insertMeta([]);
BulkMetaIO::replaceMeta([], '_variation_description', []);

t_assert($reads['fetchMeta'] === [],     'fetchMeta returns [] for no post IDs');
t_assert($reads['fetchMetaLike'] === [], 'fetchMetaLike returns [] for no post IDs');
t_assert(sql() === [], 'no method issues any SQL on empty input');
//endregion

//region Reads
reset_wpdb();
$GLOBALS['mt2mba_stub']['wpdb_results'] = [
	t_meta_row(201, '_variation_description', 'first'),
	t_meta_row(202, '_variation_description', 'second'),
];
$values = BulkMetaIO::fetchMeta([201, 202], '_variation_description');

t_assert($values === [201 => 'first', 202 => 'second'], 'fetchMeta keys values by integer post ID');
t_assert(
	sql()[0] === "SELECT post_id, meta_value FROM wp_postmeta WHERE post_id IN (201,202) AND meta_key = '_variation_description'",
	'fetchMeta builds one IN clause and binds the meta key'
);

reset_wpdb();
$GLOBALS['mt2mba_stub']['wpdb_results'] = [t_meta_row(201, 'attribute_pa_color', 'red')];
$rows = BulkMetaIO::fetchMetaLike([201], 'attribute_pa_%');

t_assert(count($rows) === 1 && $rows[0]->meta_key === 'attribute_pa_color', 'fetchMetaLike returns raw rows, meta_key included');
t_assert(
	sql()[0] === "SELECT post_id, meta_key, meta_value FROM wp_postmeta WHERE post_id IN (201) AND meta_key LIKE 'attribute_pa_%'",
	'fetchMetaLike passes the LIKE pattern through verbatim'
);
//endregion

//region Deletes — the statement shape follows the key count
reset_wpdb();
BulkMetaIO::deleteMeta([201, 202], ['_variation_description']);
t_assert(
	sql()[0] === "DELETE FROM wp_postmeta WHERE post_id IN (201,202) AND meta_key = '_variation_description'",
	'deleteMeta with one key uses "meta_key = %s"'
);

reset_wpdb();
BulkMetaIO::deleteMeta([201], ['_price', '_regular_price']);
t_assert(
	sql()[0] === "DELETE FROM wp_postmeta WHERE post_id IN (201) AND meta_key IN ('_price', '_regular_price')",
	'deleteMeta with several keys uses "meta_key IN (...)"'
);

reset_wpdb();
BulkMetaIO::deleteMetaLike(99, BulkMetaIO::likePattern('mt2mba_', '_markup_amount'));
t_assert(
	sql()[0] === "DELETE FROM wp_postmeta WHERE post_id = 99 AND meta_key LIKE 'mt2mba\\\\_%\\\\_markup\\\\_amount'",
	'deleteMetaLike targets a single post with an escaped pattern'
);
//endregion

//region likePattern escapes literals but not its own wildcard
// An unescaped 'mt2mba_' would match 'mt2mbaX...' because _ is a LIKE wildcard.
t_assert(BulkMetaIO::likePattern('mt2mba_') === 'mt2mba\\_%', 'likePattern escapes underscores in the prefix');
t_assert(BulkMetaIO::likePattern('mt2mba_', '_markup_amount') === 'mt2mba\\_%\\_markup\\_amount', 'likePattern escapes the suffix too');
t_assert(strpos(BulkMetaIO::likePattern('a', 'b'), '%') === 1, 'likePattern puts exactly one wildcard between the literals');
//endregion

//region Inserts
reset_wpdb();
BulkMetaIO::insertMeta([
	[201, '_price', '10.00'],
	[202, '_price', '20.00'],
]);
t_assert(
	sql()[0] === "INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (201, '_price', '10.00'), (202, '_price', '20.00')",
	'insertMeta writes every tuple in one statement'
);
//endregion

//region replaceMeta — the clear list is independent of the write list
// This is the behavior removeVariationPrices() depends on: it reads every
// variation's description but only rewrites the ones that carried markup, so
// deriving the DELETE from the INSERT would widen it to variations that should
// have been left alone.
reset_wpdb();
BulkMetaIO::replaceMeta([202, 203], '_variation_description', [202 => 'kept text']);
t_assert(sql() === [
	"DELETE FROM wp_postmeta WHERE post_id IN (202,203) AND meta_key = '_variation_description'",
	"INSERT INTO wp_postmeta (post_id, meta_key, meta_value) VALUES (202, '_variation_description', 'kept text')",
], 'replaceMeta clears the IDs it was given, not the ones it writes');

// Writing nothing means doing nothing — NOT "delete everything". Both callers
// skip the operation entirely when they have no rows.
reset_wpdb();
BulkMetaIO::replaceMeta([201, 202, 203], '_variation_description', []);
t_assert(sql() === [], 'replaceMeta with no rows issues no DELETE either');
//endregion

t_done();
