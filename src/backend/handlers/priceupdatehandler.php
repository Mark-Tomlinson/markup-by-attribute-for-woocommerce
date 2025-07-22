<?php
namespace mt2Tech\MarkupByAttribute\Backend\Handlers;
use mt2Tech\MarkupByAttribute\Utility as Utility;

/**
 * Handles product price increases and decreases
 * 
 * Used when modifying existing prices through WooCommerce bulk actions.
 * This handler calculates new base prices from increase/decrease operations
 * and then delegates to PriceSetHandler to reapply markups.
 *
 * @package   mt2Tech\MarkupByAttribute\Backend\Handlers
 * @author    Mark Tomlinson
 * @license   GPL-2.0+
 * @since     4.0.0
 */
class PriceUpdateHandler extends PriceMarkupHandler {
	//region INITIALIZATION
	/**
	 * Initialize PriceUpdateHandler with update information
	 * 
	 * Extracts the price change amount from the bulk action data.
	 *
	 * @since 4.0.0
	 * @param string $bulk_action The bulk action being performed
	 * @param array  $data        The update data (contains 'value' key with change amount)
	 * @param int    $product_id  The ID of the product
	 * @param array  $variations  List of variation IDs
	 */
	public function __construct($bulk_action, $data, $product_id, $variations) {
		parent::__construct($bulk_action, $product_id, is_numeric($data["value"]) ? (float) $data["value"] : 0);
	}
	//endregion

	//region PUBLIC API
	/**
	 * Process price updates and apply markups
	 * 
	 * Calculates new base price from increase/decrease amount and delegates to PriceSetHandler
	 * to reapply all markups with the new base price. Only processes products that already
	 * have markup-by-attribute metadata (base price stored).
	 *
	 * @since 4.0.0
	 * @param string $bulk_action The bulk action being performed
	 * @param array  $data        The update data
	 * @param int    $product_id  The ID of the product
	 * @param array  $variations  List of variation IDs
	 */
	public function processProductMarkups ($bulk_action, $data, $product_id, $variations) {
		// If base price metadata is present, that means the product contains variables with attribute pricing.
		$base_price = get_metadata("post", $product_id, "mt2mba_base_{$this->price_type}", true);
		if ($base_price) {
			// reapply a new base price according to the bulk action.
			// Bulk action could be any of
			//	* variable_regular_price_increase
			//	* variable_regular_price_decrease
			//	* variable_sale_price_increase
			//	* variable_sale_price_decrease
			$new_data = [];
			$new_data["value"] = $this->calculateNewBasePrice($bulk_action, $data["value"], $base_price);
			// And then loop back through changing the bulk action type to one of the two 'set price' options.
			// This will reset the prices on all variations to the new base regular/sale price plus the
			// attribute markup.
			//	* variable_regular_price
			//	* variable_sale_price
			$handler = new PriceSetHandler("variable_{$this->price_type}", $new_data, $product_id, $variations);
			$handler->processProductMarkups ($bulk_action, $data, $product_id, $variations);
		}
	}
	//endregion

	//region UTILITY METHODS
	/**
	 * Calculate new base price based on update type.
	 * Handles both percentage and fixed amount updates.
	 *
	 * @param	string	$bulk_action	The bulk action being performed
	 * @param	string	$markup			The update amount or percentage
	 * @param	float	$base_price		Current base price
	 * @return	float					New calculated base price
	 */
	private function calculateNewBasePrice($bulk_action, $markup, $base_price) {
		// Determine sign: decrease actions negate the markup value
		// e.g., "decrease by 10%" becomes -10, "increase by 5" stays +5
		$signed_data = strpos($bulk_action, "decrease") ? 0 - floatval($markup) : floatval($markup);

		// Apply markup calculation based on type
		if (strpos($markup, "%")) {
			// Percentage markup: calculate percentage of base price and add/subtract
			// Formula: new_price = base_price + (base_price * percentage / 100)
			// e.g., $100 + 10% = $100 + ($100 * 10 / 100) = $110
			return $base_price + ($base_price * $signed_data) / 100;
		} else {
			// Fixed amount markup: simply add/subtract the amount
			// e.g., $100 + $5 = $105
			return $base_price + $signed_data;
		}
	}
	//endregion
}
?>