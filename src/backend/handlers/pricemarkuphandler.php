<?php
namespace mt2Tech\MarkupByAttribute\Backend\Handlers;

/**
 * Abstract base class for markup-by-attribute product variation handling
 *
 * Provides the foundation for all markup calculation operations. This class defines the common
 * properties and initialization logic that all price handlers need, while allowing specific
 * handlers to implement their own markup calculation strategies.
 *
 * @package   mt2Tech\MarkupByAttribute\Backend\Handlers
 * @author    Mark Tomlinson
 * @license   GPL-2.0+
 * @since     4.0.0
 */
abstract class PriceMarkupHandler {
	//region PROPERTIES
	/** @var string The bulk action being performed, verbatim (e.g. variable_regular_price_decrease) */
	protected $bulk_action;

	/** @var string The type of price being processed (regular or sale) */
	protected $price_type;

	/** @var int The ID of the product being processed */
	protected $product_id;

	/** @var array The variation IDs this run operates on */
	protected $variations;

	/** @var float The base price of the product before markup */
	protected $base_price;

	/** @var string The base price formatted according to store currency settings */
	protected $base_price_formatted;

	/** @var int Number of decimal places to use in price calculations */
	protected $price_decimals;

	/**
	 * Whether this handler owns (starts/commits/rolls back) its own database transaction.
	 *
	 * MySQL does not support nested transactions — a START TRANSACTION while another
	 * is open implicitly COMMITs the open one. When a caller wraps multiple handler
	 * runs in its own transaction (e.g., Product::handleMarkupReapplication wrapping
	 * the regular- and sale-price passes), it must pass false so this handler defers
	 * transaction control to that outermost owner.
	 *
	 * @var bool
	 */
	protected $owns_transaction = true;
	//endregion

	//region INITIALIZATION
	/**
	 * Initialize the PriceMarkupHandler with everything the run needs
	 *
	 * The constructor is the only place a handler receives state. processProductMarkups()
	 * takes no arguments, so there is exactly one authoritative copy of the product ID,
	 * the variation list and the price type.
	 *
	 * @since 4.0.0
	 * @param string $bulk_action      The bulk action being performed (e.g., variable_regular_price)
	 * @param int    $product_id       The ID of the product being processed
	 * @param array  $variations       List of variation IDs for the product
	 * @param float  $base_price       The base price of the product before markup
	 * @param bool   $owns_transaction False when the caller manages the transaction
	 */
	public function __construct($bulk_action, $product_id, $variations, $base_price, $owns_transaction = true) {
		// Extract price_type from bulk_action (e.g., "variable_regular_price" -> "regular_price")
		if ($bulk_action) {
			$bulk_action_array = explode("_", $bulk_action);
			$this->price_type = $bulk_action_array[1] . "_" . $bulk_action_array[2];
		}

		$this->bulk_action = $bulk_action;
		$this->product_id = $product_id;
		$this->variations = (array) $variations;
		$this->base_price = $base_price;
		$this->base_price_formatted = is_numeric($base_price) ? strip_tags(wc_price(abs($this->base_price))) : '';
		$this->price_decimals = wc_get_price_decimals();
		$this->owns_transaction = (bool) $owns_transaction;
	}
	//endregion

	//region ABSTRACT METHODS
	/**
	 * Apply markup calculations to product variations.
	 * Must be implemented by child classes to handle specific markup scenarios.
	 *
	 * Operates entirely on constructor-supplied state.
	 */
	abstract public function processProductMarkups(): void;
	//endregion
}