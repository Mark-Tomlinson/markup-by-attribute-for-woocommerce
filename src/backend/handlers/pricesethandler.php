<?php
namespace mt2Tech\MarkupByAttribute\Backend\Handlers;
use mt2Tech\MarkupByAttribute\Utility as Utility;

/**
 * Handles setting product prices and applying markups
 *
 * Used when directly setting variation prices through WooCommerce bulk actions.
 * This handler calculates markups based on attribute terms and applies them to
 * the base price, then updates both the variation prices and descriptions.
 *
 * @package   mt2Tech\MarkupByAttribute\Backend\Handlers
 * @author    Mark Tomlinson
 * @license   GPL-2.0+
 * @since     4.0.0
 */
class PriceSetHandler extends PriceMarkupHandler {
	//region PROPERTIES
	/**
	 * @var	array	Cache for term meta to reduce database queries
	 */
	protected $term_meta_cache;
	//endregion

	//region INITIALIZATION
	/**
	 * Initialize PriceSetHandler with product and markup information
	 *
	 * Extracts the base price from the bulk action data and initializes the parent handler.
	 *
	 * @since 4.0.0
	 * @param string $bulk_action The bulk action being performed
	 * @param array  $data        The data for price setting (contains 'value' key)
	 * @param int    $product_id  The ID of the product
	 * @param array  $variations  List of variation IDs
	 */
	public function __construct($bulk_action, $data, $product_id, $variations) {
		// Convert localized decimal input to standardized format using WooCommerce
		$cleaned_value = wc_format_decimal($data["value"], false, true);
		parent::__construct($bulk_action, $product_id, is_numeric($cleaned_value) ? (float) $cleaned_value : '');
	}
	//endregion

	//region PUBLIC API
	/**
	 * Process markup calculations and apply them to variations
	 *
	 * Core method that coordinates the entire markup calculation workflow:
	 * 1. Validates the base price is not blank/zero (unless zero is allowed)
	 * 2. Retrieves product attributes and builds markup calculation table
	 * 3. Processes each variation to calculate final prices with markups
	 * 4. Bulk updates all variation prices and descriptions in the database
	 *
	 * @since 4.0.0
	 * @param string $bulk_action The bulk action being performed
	 * @param array  $data        The pricing data for the operation
	 * @param int    $product_id  The ID of the product
	 * @param array  $variations  List of variation IDs
	 */
	public function processProductMarkups($bulk_action, $data, $product_id, $variations) {
		global $mt2mba_utility;

		// Was the price removed from variations, or is the price zero and zero is allowed?
		if ($this->isBlankOrZeroPrice($product_id, $variations)) {
			return;		// Yes, no further processing necessary
		}

		// Retrieve all attributes and their terms for the product
		$attribute_data = $this->getAttributeData($product_id);

		// Build a table of the markup values for the product
		$markup_table = $this->buildMarkupTable($attribute_data, $product_id);

		// Bulk save product markup values
		if ($this->price_type === REGULAR_PRICE) {
			$this->bulkSaveProductMarkupValues($markup_table);
		}

		$rounded_base = round($this->base_price, $this->price_decimals);
		$base_price_description = $this->handleBasePriceUpdate($product_id, $rounded_base);

		// Process each variation
		$variation_updates = [];
		foreach ($variations as $variation_id) {
			$variation_updates[] = $this->processVariation($variation_id, $markup_table, $base_price_description);
		}

		// Bulk update all variations from the variations_update table
		if (!empty($variation_updates)) {
			$this->updateVariationPricesAndDescriptions($variation_updates);
		}
	}
	//endregion

	//region VALIDATION & SANITIZATION
	/**
	 * Check if price was blanked out or zero, and clean up metadata if so
	 *
	 * Handles special cases where the base price is empty, zero, or negative.
	 * If the price is being cleared, this method removes all markup metadata
	 * and variation descriptions to prevent orphaned data.
	 *
	 * @since 4.0.0
	 * @param int   $product_id The ID of the product
	 * @param array $variations List of variation IDs
	 * @return bool             True if price is blank/zero and processing should stop
	 */
	public function isBlankOrZeroPrice($product_id, $variations) {
		// Condition #1: {base-price} is blank or =< 0
		if (floatval($this->base_price) <= 0) {

			// If {base-price} is numeric (meaning 0 or negative),
			if (is_numeric($this->base_price)) {

				// if zero and ALLOW-ZERO is true,
				if ($this->base_price == 0 && MT2MBA_ALLOW_ZERO === 'yes') {
					// Set {price_type} base price metadata to 0
					// update_post_meta() does not appear to change cached records. Deleting the
					// record before rewriting it appears to be the only way to update the cache.
					delete_post_meta($product_id, "mt2mba_base_{$this->price_type}");
					update_post_meta($product_id, "mt2mba_base_{$this->price_type}", 0);
					// Fall through to Regular Price check

				// If negative or ALLOW-ZERO is false, continue processing the markup
				} else {
					// Else {base-price} is > 0 or Allow Zero is false, continue markup logic
					return false;
				}

			} else {	// Else ({base_price} is not numeric),
				// Remove {price_type} base price metadata
				delete_post_meta($product_id, "mt2mba_base_{$this->price_type}");
				// Fall through to Regular Price check
			}

			// If {price_type} is Regular Price (regardless of blank or zero)
			if ($this->price_type == REGULAR_PRICE) {

				// Remove Sales Price metadata
				delete_post_meta($product_id, "mt2mba_base_" . SALE_PRICE);

				// Loop through variations to remove markup information
				global $mt2mba_utility;
				foreach ($variations as $variation_id) {
					$variation = wc_get_product($variation_id);
					if (!$variation) continue;		// Skip if product not found

					$description = $variation->get_description();
					$markup_pos = strpos($description, PRODUCT_MARKUP_DESC_BEG);

					// If no markup information, skip variation
					if ($markup_pos === false) {
						continue;
					}

					// If the description begins with markup information, delete the description
					if ($markup_pos === 0) {
						$new_description = '';
					// Otherwise, strip the markup information from the description
					} else {
						$new_description = $mt2mba_utility->remove_bracketed_string(
							PRODUCT_MARKUP_DESC_BEG,
							PRODUCT_MARKUP_DESC_END,
							$description
						);
					}

					// Update the variation with the new description
					$variation->set_description($new_description);
					$variation->save();

				}	// END foreach ($variations as $variation_id)
			}
			// Do not continue markup logic
			return true;
		}

		// Else {base-price} is > 0, continue markup logic
		return false;
	}
	//endregion

	//region MARKUP CALCULATIONS
	/**
	 * Build markup table for calculations
	 *
	 * Creates a structured array containing calculated markup values for each attribute term.
	 * This method processes both percentage and fixed markups, applying appropriate rounding
	 * and business logic based on plugin settings.
	 *
	 * @since 4.0.0
	 * @param array $attribute_data Array of attributes with labels and terms
	 * @param int   $product_id     The ID of the product
	 * @return array                Markup table indexed by [taxonomy][term_slug] with markup/description data
	 */
	protected function buildMarkupTable($attribute_data, $product_id) {
		global $mt2mba_utility;
		$markup_table = [];

		foreach ($attribute_data as $taxonomy => $data) {
			$attrb_label = $data['label'];
			foreach ($data['terms'] as $term) {
				$markup = get_term_meta($term->term_id, 'mt2mba_markup', true);

				if (!empty($markup)) {
					// Determine price to calculate markup against based on settings
					if ($this->price_type === REGULAR_PRICE || MT2MBA_SALE_PRICE_MARKUP === 'yes') {
						$price = $this->base_price;
					} else {
						$price = get_metadata("post", $product_id, "mt2mba_base_" . REGULAR_PRICE, true);
					}

					// Calculate markup value: percentage markups are calculated against the price,
					// fixed markups are used as-is
					if (strpos($markup, "%")) {
						$markup_value = ($price * floatval($markup)) / 100;
					} else {
						$markup_value = floatval($markup);
					}

					// Round markup value based on plugin settings
					$markup_value = MT2MBA_ROUND_MARKUP == "yes" ? round($markup_value, 0) : round($markup_value, $this->price_decimals);

					if ($markup_value != 0) {
						$markup_table[$taxonomy][$term->slug] = [
							'term_id' => $term->term_id,
							'markup' => $markup_value,
						];

						// Add description if not ignored (for both regular and sale prices)
						if (MT2MBA_DESC_BEHAVIOR !== "ignore") {
							$markup_table[$taxonomy][$term->slug]['description'] =
								$mt2mba_utility->formatVariationMarkupDescription(
									$markup_value,
									$attrb_label,
									$term->name
								);
						}
					}
				}
			}
		}
		return $markup_table;
	}

	/**
	 * Save the base price and generate price description.
	 * Updates metadata and handles transient storage for current base price.
	 *
	 * @param	int		$product_id		The ID of the product
	 * @param	float	$rounded_base	The rounded base price to save
	 * @return	string					Price description or empty string based on settings
	 */
	private function handleBasePriceUpdate($product_id, $rounded_base) {
		// update_post_meta() does not appear to change cached records. Deleting the
		// record before rewriting it appears to be the only way to update the cache.
		delete_post_meta($product_id, "mt2mba_base_{$this->price_type}");
		update_post_meta($product_id, "mt2mba_base_{$this->price_type}", $rounded_base);
		if ($this->price_type === REGULAR_PRICE) {
			set_transient('mt2mba_current_base_' . $product_id, $rounded_base, HOUR_IN_SECONDS);
		}
		return MT2MBA_HIDE_BASE_PRICE === 'no' ?
			html_entity_decode(MT2MBA_PRICE_META . $this->getRegularPriceForDescription($product_id)) . PHP_EOL : '';
	}

	/**
	 * Process a single variation's price and description.
	 * Calculates final price and builds description based on markup table.
	 *
	 * @param	int		$variation_id			The ID of the variation
	 * @param	array	$markup_table			The markup calculations table
	 * @param	string	$base_price_description	Base price description text
	 * @return	array							Processed variation data
	 */
	private function processVariation($variation_id, $markup_table, $base_price_description) {
		global $mt2mba_utility;
		// Clear WooCommerce caches to ensure fresh data, especially for sale price operations
		wp_cache_delete($variation_id, 'posts');
		wp_cache_delete($variation_id, 'post_meta');
		wc_delete_product_transients($variation_id);

		// Force fresh load of variation to avoid cached description data
		$variation = wc_get_product($variation_id);
		$variation_price = $this->base_price;
		$markup_description = '';

		foreach ($variation->get_attributes() as $attribute_id => $term_id) {
			if (isset($markup_table[$attribute_id][$term_id])) {
				$markup = (float) $markup_table[$attribute_id][$term_id]["markup"];
				$variation_price += $markup;
				if (isset($markup_table[$attribute_id][$term_id]["description"])) {
					$markup_description .= $markup_table[$attribute_id][$term_id]["description"] . PHP_EOL;
				}
			}
		}

		$description = $this->buildVariationDescription($variation, $base_price_description, $markup_description, $variation_price);

		return [
			'id' => $variation_id,
			'price' => $variation_price,
			'description' => trim($description)
		];
	}

	/**
	 * Build variation description with markup information.
	 *
	 * For regular prices: Builds new descriptions with current markup calculations using regular price as base.
	 * For sale prices: Preserves existing descriptions to maintain consistent regular price markup display.
	 * This ensures descriptions always show how the regular price was calculated, regardless of current sale prices.
	 *
	 * @param	WC_Product	$variation				The variation product object
	 * @param	string		$base_price_description	Base price description text (regular price)
	 * @param	string		$markup_description		Markup-specific description text
	 * @param	float		$variation_price		The calculated variation price
	 * @return	string								Complete variation description
	 */
	protected function buildVariationDescription($variation, $base_price_description, $markup_description, $variation_price) {
		global $mt2mba_utility;

		if ($this->price_type === REGULAR_PRICE) {
			// Build new description for regular prices and reapply markup operations
			$description = "";

			// Preserve existing non-markup description content unless overwriting
			if (MT2MBA_DESC_BEHAVIOR !== "overwrite") {
				$description = $variation->get_description();
				$description = $mt2mba_utility->remove_bracketed_string(
					PRODUCT_MARKUP_DESC_BEG,
					PRODUCT_MARKUP_DESC_END,
					$description
				);
			}

			// Add separator if description has content
			if (!empty($description)) {
				$description .= PHP_EOL;
			}

			// Add markup information if we have markups and behavior allows it
			if ($markup_description && $variation_price != null && MT2MBA_DESC_BEHAVIOR !== "ignore") {
				$description .= PRODUCT_MARKUP_DESC_BEG .
							$base_price_description .
							$markup_description .
							PRODUCT_MARKUP_DESC_END;
			}

			return trim($description);
		} else {
			// For sale prices: preserve existing description to maintain regular price markup consistency
			return $variation->get_description();
		}
	}
	//endregion

	//region DATABASE OPERATIONS
	/**
	 * Apply markup value updates to the product.
	 *
	 * @param	array	$markup_table	The markup table for the product
	 */
	protected function bulkSaveProductMarkupValues($markup_table) {
		global $wpdb;

		// Delete all existing mt2mba_{term_id}_markup_amount records for this product
		$wpdb->query($wpdb->prepare(
			"DELETE FROM {$wpdb->postmeta}
			WHERE post_id = %d
			AND meta_key LIKE 'mt2mba_%_markup_amount'",
			$this->product_id
		));

		// Build queries, then bulk insert new mt2mba_{term_id}_markup_amount into postmeta.
		$meta_data = [];

		foreach ($markup_table as $attribute => $options) {
			foreach ($options as $option => $details) {
				$term_id = $details['term_id'];
				$markup = number_format(floatval($details['markup']), $this->price_decimals, '.', '');
				$meta_key = "mt2mba_{$term_id}_markup_amount";
				$meta_data[] = $wpdb->prepare("(%d, %s, %s)", $this->product_id, $meta_key, $markup);
			}
		}

		if (!empty($meta_data)) {
			// Bulk insert new records
			$wpdb->query("
				INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
				VALUES " . implode(", ", $meta_data)
			);
		}
	}

	/**
	 * Bulk update variation prices and descriptions in the database
	 *
	 * Performs efficient bulk database updates using transactions to ensure data consistency.
	 * Updates both _price and _regular_price/_sale_price meta fields, plus variation descriptions.
	 * Uses DELETE + INSERT pattern for better performance than individual UPDATEs.
	 *
	 * @since 4.0.0
	 * @param array $updates Array of variation data with id, price, and description keys
	 */
	protected function updateVariationPricesAndDescriptions($updates) {
		global $wpdb;

		$variation_ids = [];
		$price_inserts = [];
		$description_updates = [];

		// Build arrays for our SQL operations
		foreach ($updates as $update) {
			$variation_ids[] = (int)$update['id'];

			// Reformat price if not null
			if ($update['price'] !== null) {
				$update['price'] = number_format($update['price'], $this->price_decimals, '.', '');
			}

			// Each variation needs both '_price' and price type records
			$price_inserts[] = $wpdb->prepare(
				"(%d, %s, %s),
				(%d, %s, %s)",
				$update['id'],
				'_price',
				$update['price'],
				$update['id'],
				'_' . $this->price_type,
				$update['price']
			);

			if (isset($update['description'])) {
				// Preserve allowed HTML tags (span with id attribute) while sanitizing content
				$allowed_html = array(
					'span' => array(
						'id' => array()
					)
				);
				$sanitized_description = wp_kses($update['description'], $allowed_html);
				$description_updates[] = $wpdb->prepare(
					"(%d, '_variation_description', %s)",
					$update['id'],
					$sanitized_description
				);
			}
		}

		// Start transaction for data consistency
		$wpdb->query('START TRANSACTION');

		try {
			// Delete existing price records first
			if (!empty($variation_ids)) {
				$placeholders = array_fill(0, count($variation_ids), '%d');
				$meta_keys = array('_price', '_' . $this->price_type);

				$wpdb->query($wpdb->prepare(
					"DELETE FROM {$wpdb->postmeta}
					WHERE post_id IN (" . implode(',', $placeholders) . ")
					AND meta_key IN (%s, %s)",
					array_merge($variation_ids, $meta_keys)
				));
			}

			// Insert new price records
			if (!empty($price_inserts)) {
				$wpdb->query(
					"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
					VALUES " . implode(", ", $price_inserts)
				);
			}

			// Handle descriptions for both regular and sale price updates
			if (!empty($description_updates)) {
				// Remove existing descriptions
				$wpdb->query($wpdb->prepare(
					"DELETE FROM {$wpdb->postmeta}
					WHERE post_id IN (" . implode(',', array_fill(0, count($variation_ids), '%d')) . ")
					AND meta_key = '_variation_description'",
					$variation_ids
				));

				// Insert new descriptions
				$wpdb->query(
					"INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
					VALUES " . implode(", ", $description_updates)
				);
			}

			$wpdb->query('COMMIT');

		} catch (Exception $e) {
			$wpdb->query('ROLLBACK');
			throw $e;
		}
	}
	//endregion

	//region UTILITY METHODS
	/**
	 * Get formatted regular price for description display.
	 * Always returns the regular price formatting, regardless of which price type is being set.
	 *
	 * @param	int		$product_id	The ID of the product
	 * @return	string				Formatted regular price for description
	 */
	private function getRegularPriceForDescription($product_id) {
		if ($this->price_type === REGULAR_PRICE) {
			// We're setting regular price, use the current value being set
			return $this->base_price_formatted;
		} else {
			// We're setting sale price, get stored regular price
			$regular_price = get_metadata("post", $product_id, "mt2mba_base_" . REGULAR_PRICE, true);
			return is_numeric($regular_price) ? strip_tags(wc_price(abs($regular_price))) : '';
		}
	}

	/**
	 * Get attribute data for a product.
	 * Retrieves and formats all taxonomy attribute information.
	 *
	 * @param	int		$product_id	The ID of the product
	 * @return	array				Formatted attribute data with labels and terms
	 */
	private function getAttributeData($product_id) {
		$attribute_data = [];
		foreach (wc_get_product($product_id)->get_attributes() as $pa_attrb) {
			if ($pa_attrb->is_taxonomy()) {
				$taxonomy = $pa_attrb->get_name();
				$attribute_data[$taxonomy] = [
					'label' => wc_attribute_label($taxonomy),
					'terms' => get_terms([
						"taxonomy" => $taxonomy,
						"hide_empty" => false
					])
				];
			}
		}
		return $attribute_data;
	}
	//endregion
}
?>