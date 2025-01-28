<?php
namespace mt2Tech\MarkupByAttribute\Backend\Handlers;

/**
 * Handles deletion of markup metadata.
 * Used when removing variations to clean up associated markup data.
 *
 * @package mt2Tech\MarkupByAttribute\Backend\Handlers
 */
class MarkupDeleteHandler extends PriceMarkupHandler {
	/**
	 * Initialize MarkupDeleteHandler.
	 * Overrides parent constructor to prevent initialization.
	 *
	 * @param	string	$var1		Empty string (unused)
	 * @param	string	$var2		Empty string (unused)
	 * @param	int		$product_id	The ID of the product
	 * @param	array	$var4		Empty array (unused)
	 */
	public function __construct($var1, $var2, $product_id, $var4) {
		// Nessacary __construct() to prevent parent::__construct() from firing
	}

	/**
	 * Delete all markup metadata for a product.
	 * Cleans up markup data when variations are deleted.
	 *
	 * @param	string	$var1		Empty string (unused)
	 * @param	string	$var2		Empty string (unused)
	 * @param	int		$product_id	The ID of the product
	 * @param	array	$var4		Empty array (unused)
	 */
	public function processProductMarkups($var1, $var2, $product_id, $var4) {
		// Delete all Markup-by-Attribute metadata for product
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->postmeta} WHERE post_id = '{$product_id}' AND meta_key LIKE 'mt2mba_%'"
		);
	}
}
?>