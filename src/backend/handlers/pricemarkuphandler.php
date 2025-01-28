<?php
namespace mt2Tech\MarkupByAttribute\Backend\Handlers;
use mt2Tech\MarkupByAttribute\Utility as Utility;

/**
 * Abstract base class that provides the foundation for markup-by-attribute product variation handling.
 * Creates a shell with basic functions that are extended by specific handler classes based on
 * the type of bulk editing operation being performed.
 *
 * @package mt2Tech\MarkupByAttribute\Backend\Handlers
 */
abstract class PriceMarkupHandler {
	/** @var string The type of price being processed (regular or sale) */
	protected $price_type;
  
	/** @var int The ID of the product being processed */
	protected $product_id;
  
	/** @var float The base price of the product before markup */
	protected $base_price;
  
	/** @var string The base price formatted according to store currency settings */
	protected $base_price_formatted;
  
	/** @var int Number of decimal places to use in price calculations */
	protected $price_decimals;

	/**
	 * Initialize the PriceMarkupHandler with product information.
	 *
	 * @param	string	$bulk_action	The bulk action being performed (e.g., variable_regular_price)
	 * @param	int		$product_id		The ID of the product being processed
	 * @param	float	$base_price		The base price of the product before markup
	 */
	public function __construct($bulk_action, $product_id, $base_price) {
		// Create 'regular_price' string in one place
		if (!defined('REGULAR_PRICE')) {
			define('REGULAR_PRICE', 'regular_price');
		}
		if (!defined('SALE_PRICE')) {
			define('SALE_PRICE', 'sale_price');
		}

		// Extract price_type from bulk_action
		if ($bulk_action) {
			$bulk_action_array = explode("_", $bulk_action);
			$this->price_type = $bulk_action_array[1] . "_" . $bulk_action_array[2];
		}

		$this->product_id = $product_id;
		$this->base_price = $base_price;
		$this->base_price_formatted = is_numeric($base_price) ? strip_tags(wc_price(abs($this->base_price))) : '';
		$this->price_decimals = wc_get_price_decimals();
	}

	/**
	 * Apply markup calculations to product variations.
	 * Must be implemented by child classes to handle specific markup scenarios.
	 *
	 * @param	string	$bulk_action	The bulk action being performed
	 * @param	array	$data			Values passed from JavaScript popup
	 * @param	int		$product_id		The ID of the product
	 * @param	array	$variations		List of variation IDs for the product
	 */
	abstract public function processProductMarkups($price_type, $data, $product_id, $variations);
}
?>