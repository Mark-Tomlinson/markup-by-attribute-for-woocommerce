<?php
namespace mt2Tech\MarkupByAttribute\Backend\Handlers;

/**
 * Bulk postmeta reads and writes for the markup handlers
 *
 * The price handlers touch hundreds of meta rows per reprice, so they bypass the
 * WordPress meta API and issue their own multi-row statements. That raw SQL used
 * to be spread across the handlers, which meant the IN-clause placeholder dance
 * appeared six times and the DELETE+INSERT pair four times — each one a separate
 * chance to forget the empty-array guard and emit "WHERE post_id IN ()".
 *
 * Every method here no-ops on empty input, so that guard exists once.
 *
 * Deliberately does NOT manage transactions. Callers wrap these calls in their
 * own START TRANSACTION/COMMIT and decide who owns it (see PriceSetHandler's
 * $owns_transaction), which only works if this class stays a dumb statement
 * issuer.
 *
 * @package   mt2Tech\MarkupByAttribute\Backend\Handlers
 * @since     4.7.0
 */
final class BulkMetaIO {
	//region READS
	/**
	 * Fetch one meta key across many posts
	 *
	 * @since  4.7.0
	 * @param  array  $post_ids Post IDs to read
	 * @param  string $meta_key The meta key to read
	 * @return array            [post_id => meta_value], empty if no IDs given
	 */
	public static function fetchMeta(array $post_ids, string $meta_key): array {
		global $wpdb;

		$ids = self::normalizeIds($post_ids);
		if (empty($ids)) {
			return [];
		}

		$rows = $wpdb->get_results($wpdb->prepare(
			"SELECT post_id, meta_value FROM {$wpdb->postmeta}
			WHERE post_id IN (" . self::placeholders($ids) . ') AND meta_key = %s',
			array_merge($ids, [$meta_key])
		));

		$values = [];
		foreach ($rows as $row) {
			$values[(int) $row->post_id] = $row->meta_value;
		}
		return $values;
	}

	/**
	 * Fetch every meta row matching a LIKE pattern across many posts
	 *
	 * Returns raw rows because the caller needs meta_key as well as the value.
	 * The pattern is used verbatim — pass it through likePattern() first if the
	 * literal parts contain underscores that must not act as wildcards.
	 *
	 * @since  4.7.0
	 * @param  array  $post_ids Post IDs to read
	 * @param  string $key_like LIKE pattern for meta_key
	 * @return array            Rows of (post_id, meta_key, meta_value)
	 */
	public static function fetchMetaLike(array $post_ids, string $key_like): array {
		global $wpdb;

		$ids = self::normalizeIds($post_ids);
		if (empty($ids)) {
			return [];
		}

		return $wpdb->get_results($wpdb->prepare(
			"SELECT post_id, meta_key, meta_value FROM {$wpdb->postmeta}
			WHERE post_id IN (" . self::placeholders($ids) . ') AND meta_key LIKE %s',
			array_merge($ids, [$key_like])
		));
	}
	//endregion

	//region WRITES
	/**
	 * Delete the given meta keys from the given posts
	 *
	 * @since 4.7.0
	 * @param array $post_ids  Post IDs to clear
	 * @param array $meta_keys Meta keys to remove
	 */
	public static function deleteMeta(array $post_ids, array $meta_keys): void {
		global $wpdb;

		$ids = self::normalizeIds($post_ids);
		if (empty($ids) || empty($meta_keys)) {
			return;
		}

		// One key reads as "= %s"; several as "IN (...)". Same result either way,
		// but the single-key form is what shows up in a slow-query log.
		$key_clause = count($meta_keys) === 1
			? 'meta_key = %s'
			: 'meta_key IN (' . implode(', ', array_fill(0, count($meta_keys), '%s')) . ')';

		$wpdb->query($wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta}
			WHERE post_id IN (" . self::placeholders($ids) . ")
			AND $key_clause",
			array_merge($ids, array_values($meta_keys))
		));
	}

	/**
	 * Delete every meta key matching a LIKE pattern from one post
	 *
	 * Single-post by design: both callers sweep a whole product's markup meta,
	 * never a batch of variations.
	 *
	 * @since 4.7.0
	 * @param int    $post_id  The post to sweep
	 * @param string $key_like LIKE pattern for meta_key (see likePattern())
	 */
	public static function deleteMetaLike(int $post_id, string $key_like): void {
		global $wpdb;

		$wpdb->query($wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta}
			WHERE post_id = %d
			AND meta_key LIKE %s",
			$post_id,
			$key_like
		));
	}

	/**
	 * Insert meta rows in a single statement
	 *
	 * @since 4.7.0
	 * @param array $tuples List of [post_id, meta_key, meta_value] triples
	 */
	public static function insertMeta(array $tuples): void {
		global $wpdb;

		if (empty($tuples)) {
			return;
		}

		$values = [];
		foreach ($tuples as $tuple) {
			$values[] = $tuple[0];
			$values[] = $tuple[1];
			$values[] = $tuple[2];
		}

		$wpdb->query($wpdb->prepare(
			"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES "
			. implode(', ', array_fill(0, count($tuples), '(%d, %s, %s)')),
			$values
		));
	}

	/**
	 * Clear one meta key from a set of posts, then write new values
	 *
	 * The clear list and the write list are separate on purpose. When the base
	 * price is blanked out, only the variations that actually carried markup text
	 * are cleared, while the read that found them covered every variation —
	 * deriving the clear list from $rows would silently widen that DELETE.
	 *
	 * Writing nothing means doing nothing: both callers skip the whole operation
	 * when they have no rows, rather than treating it as "delete everything".
	 *
	 * @since 4.7.0
	 * @param array  $clear_post_ids Posts whose existing row is removed
	 * @param string $meta_key       The meta key being replaced
	 * @param array  $rows           [post_id => meta_value] to write
	 */
	public static function replaceMeta(array $clear_post_ids, string $meta_key, array $rows): void {
		if (empty($rows)) {
			return;
		}

		self::deleteMeta($clear_post_ids, [$meta_key]);

		$tuples = [];
		foreach ($rows as $post_id => $value) {
			$tuples[] = [(int) $post_id, $meta_key, $value];
		}
		self::insertMeta($tuples);
	}
	//endregion

	//region HELPERS
	/**
	 * Build a LIKE pattern whose literal parts are escaped
	 *
	 * Underscores are LIKE wildcards, so 'mt2mba_' would match 'mt2mbaX'. Only
	 * the '%' this inserts between the two literals is meant to be a wildcard.
	 *
	 * @since  4.7.0
	 * @param  string $prefix Literal text the key starts with
	 * @param  string $suffix Literal text the key ends with, if any
	 * @return string         Escaped pattern for a LIKE comparison
	 */
	public static function likePattern(string $prefix, string $suffix = ''): string {
		global $wpdb;

		return $wpdb->esc_like($prefix) . '%' . ($suffix === '' ? '' : $wpdb->esc_like($suffix));
	}

	/** Cast to int and drop nothing else — IDs reach SQL as %d regardless. */
	private static function normalizeIds(array $post_ids): array {
		return array_map('intval', $post_ids);
	}

	/** '%d, %d, %d' for an IN clause. */
	private static function placeholders(array $ids): string {
		return implode(',', array_fill(0, count($ids), '%d'));
	}
	//endregion
}
