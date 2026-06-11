<?php
namespace mt2Tech\MarkupByAttribute\Backend\Handlers;

/**
 * Handles cleanup of markup metadata for the "Delete all variations" bulk action
 *
 * Fires from the WooCommerce variations bulk-edit dropdown via the `delete_all`
 * action (see Product::handleBulkPriceAction). When all of a product's variations
 * are removed the parent product survives, but its per-term markup metadata is now
 * orphaned, so this handler strips it. Note: this does NOT run on full product
 * deletion — WordPress core removes the post's meta in that case.
 *
 * @package   mt2Tech\MarkupByAttribute\Backend\Handlers
 * @author    Mark Tomlinson
 * @license   GPL-2.0+
 * @since     4.0.0
 */
class MarkupDeleteHandler extends PriceMarkupHandler {
	//region INITIALIZATION
	/**
	 * Initialize MarkupDeleteHandler
	 *
	 * Overrides parent constructor to prevent initialization since delete operations
	 * don't need price calculation setup.
	 *
	 * @since 4.0.0
	 * @param string $unused1    Unused parameter (maintaining interface compatibility)
	 * @param string $unused2    Unused parameter (maintaining interface compatibility)
	 * @param int    $product_id The ID of the product
	 * @param array  $unused4    Unused parameter (maintaining interface compatibility)
	 */
	public function __construct($unused1, $unused2, $product_id, $unused4) {
		// Necessary __construct() to prevent parent::__construct() from firing
	}
	//endregion

	//region PUBLIC API
	/**
	 * Delete all markup metadata from a product
	 *
	 * Invoked by the "Delete all variations" bulk action (`delete_all`). Removes the
	 * parent product's mt2mba_* metadata, which becomes orphaned once its variations
	 * are gone. This is not a post-deletion hook — deleting the product itself is
	 * cleaned up by WordPress core.
	 *
	 * @since 4.0.0
	 * @param string $unused1    Unused parameter (maintaining interface compatibility)
	 * @param string $unused2    Unused parameter (maintaining interface compatibility)
	 * @param int    $product_id The ID of the product
	 * @param array  $unused4    Unused parameter (maintaining interface compatibility)
	 */
	public function processProductMarkups($unused1, $unused2, $product_id, $unused4): void {
		global $wpdb;

		// Delete all Markup-by-Attribute metadata for the product using prepared statement
		$wpdb->query($wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE %s",
			$product_id,
			$wpdb->esc_like('mt2mba_') . '%'
		));
	}
	//endregion
}