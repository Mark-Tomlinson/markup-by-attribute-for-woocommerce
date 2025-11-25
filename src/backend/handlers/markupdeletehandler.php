<?php
namespace mt2Tech\MarkupByAttribute\Backend\Handlers;

/**
 * Handles deletion of markup metadata
 *
 * Used when removing variations to clean up associated markup data.
 * This handler ensures that orphaned metadata is properly removed from
 * the database when products or variations are deleted.
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
	 * Delete all markup metadata for a product
	 *
	 * Removes all markup-by-attribute metadata from the database when products
	 * or variations are deleted. This prevents orphaned data accumulation.
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
			"DELETE FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key LIKE 'mt2mba_%'",
			$product_id
		));
	}
	//endregion
}
?>