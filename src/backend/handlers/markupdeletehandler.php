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
	 * Deliberately does not call parent::__construct(): deletion needs no price
	 * type, base price or currency formatting, only the product to sweep.
	 *
	 * @since 4.0.0
	 * @param int $product_id The ID of the product
	 */
	public function __construct($product_id) {
		$this->product_id = $product_id;
		// This action fires once the variations are already gone
		$this->variations = [];
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
	 */
	public function processProductMarkups(): void {
		// Delete all Markup-by-Attribute metadata for the product
		BulkMetaIO::deleteMetaLike($this->product_id, BulkMetaIO::likePattern('mt2mba_'));
	}
	//endregion
}