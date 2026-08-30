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
 * @since     4.0.0
 */
class PriceUpdateHandler extends PriceMarkupHandler {
	//region PROPERTIES
	/**
	 * The raw bulk-edit data, kept because calculateNewBasePrice() needs the
	 * value exactly as WooCommerce received it — it runs its own locale-aware
	 * normalization, which wc_format_decimal() would have already flattened.
	 *
	 * @var array
	 */
	private $data;
	//endregion

	//region INITIALIZATION
	/**
	 * Initialize PriceUpdateHandler with update information
	 *
	 * Extracts the price change amount from the bulk action data.
	 *
	 * @since 4.0.0
	 * @param string $bulk_action      The bulk action being performed
	 * @param array  $data             The update data (contains 'value' key with change amount)
	 * @param int    $product_id       The ID of the product
	 * @param array  $variations       List of variation IDs
	 * @param bool   $owns_transaction False when the caller manages the transaction
	 */
	public function __construct($bulk_action, $data, $product_id, $variations, $owns_transaction = true) {
		$this->data = $data;
		// Convert localized decimal input to standardized format using WooCommerce
		$cleaned_value = wc_format_decimal($data['value'], false, true);
		parent::__construct(
			$bulk_action,
			$product_id,
			$variations,
			is_numeric($cleaned_value) ? (float) $cleaned_value : 0,
			$owns_transaction
		);
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
	 */
	public function processProductMarkups(): void {
		// If base price metadata is present, that means the product contains variables with attribute pricing.
		$base_price = get_metadata('post', $this->product_id, "mt2mba_base_{$this->price_type}", true);
		if ($base_price) {
			// reapply a new base price according to the bulk action.
			// Bulk action could be any of
			//	* variable_regular_price_increase
			//	* variable_regular_price_decrease
			//	* variable_sale_price_increase
			//	* variable_sale_price_decrease
			$new_data = [];
			$new_data['value'] = $this->calculateNewBasePrice($this->bulk_action, $this->data['value'], $base_price);
			// And then loop back through changing the bulk action type to one of the two 'set price' options.
			// This will reset the prices on all variations to the new base regular/sale price plus the
			// attribute markup.
			//	* variable_regular_price
			//	* variable_sale_price
			// Transaction ownership is forwarded: if a caller wrapped us in its own
			// transaction, the handler we delegate to must not start a nested one.
			$handler = new PriceSetHandler(
				"variable_{$this->price_type}",
				$new_data,
				$this->product_id,
				$this->variations,
				$this->owns_transaction
			);
			$handler->processProductMarkups();
		}
	}
	//endregion

	//region UTILITY METHODS
	/**
	 * Calculate new base price based on update type.
	 * Handles both percentage and fixed amount updates.
	 *
	 * The value arrives raw from WooCommerce's bulk-edit prompt, so it is
	 * normalized here rather than validated: WooCommerce has already applied its
	 * own price change by the time this hook runs, and refusing to reapply markups
	 * would leave the product half-updated. Best effort keeps prices consistent —
	 * worst case we land on the same reading WooCommerce used. wc_format_decimal()
	 * is WooCommerce's own parser, so the store's separators are honored exactly
	 * as WooCommerce honored them.
	 *
	 * @param	string	$bulk_action	The bulk action being performed
	 * @param	string	$markup			The update amount or percentage
	 * @param	float	$base_price		Current base price
	 * @return	float					New calculated base price
	 */
	private function calculateNewBasePrice($bulk_action, $markup, $base_price): float {
		// Rewrite locale notation ("%50", "5 %", "1 000,50") into canonical form.
		// Without this, floatval("%50") is 0 and the price silently never changes.
		$normalized = Utility\General::normalizeMarkupNotation((string) $markup);

		// Strip the percent sign, then move the decimal point to '.' so floatval()
		// reads it. No wc_format_decimal() — normalizeMarkupNotation() has already
		// dealt with the store's thousands separator.
		$is_percentage = Utility\General::isPercentage($normalized);
		$numeric = $is_percentage ? substr($normalized, 0, -1) : $normalized;
		$amount = (float) Utility\General::toInternalDecimal($numeric);

		// Determine sign: decrease actions negate the markup value
		// e.g., "decrease by 10%" becomes -10, "increase by 5" stays +5
		$is_decrease = strpos($bulk_action, 'decrease') !== false;
		$signed_data = $is_decrease ? 0 - $amount : $amount;

		// Apply markup calculation based on type
		if ($is_percentage) {
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