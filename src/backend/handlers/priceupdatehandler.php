<?php
namespace mt2Tech\MarkupByAttribute\Backend\Handlers;
use mt2Tech\MarkupByAttribute\Utility as Utility;

/**
 * Handles product price increases and decreases.
 * Used when modifying existing prices through bulk actions.
 */
class PriceUpdateHandler extends PriceMarkupHandler {
	/**
	 * Initialize PriceUpdateHandler with update information.
	 *
	 * @param	string	$bulk_action	The bulk action being performed
	 * @param	array	$data			The update data
	 * @param	int		$product_id		The ID of the product
	 * @param	array	$variations		List of variation IDs
	 */
	public function __construct($bulk_action, $data, $product_id, $variations) {
		parent::__construct($bulk_action, $product_id, is_numeric($data["value"]) ? (float) $data["value"] : 0);
	}

	/**
	 * Process price updates and apply markups.
	 * Recalculates base price and reapplies markups accordingly.
	 *
	 * @param	string	$bulk_action	The bulk action being performed
	 * @param	array	$data			The update data
	 * @param	int		$product_id		The ID of the product
	 * @param	array	$variations		List of variation IDs
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
		// Indicate whether we are increasing or decreasing
		$signed_data = strpos($bulk_action, "decrease") ? 0 - floatval($markup) : floatval($markup);

		// Calc based on whether it is a percentage or fixed number
		if (strpos($markup, "%")) {
			return $base_price + ($base_price * $signed_data) / 100;
		} else {
			return $base_price + $signed_data;
		}
	}
}
?>