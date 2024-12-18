<?php
namespace mt2Tech\MarkupByAttribute\Backend\Handlers;
use mt2Tech\MarkupByAttribute\Utility as Utility;

/**
 * Handles setting product prices and applying markups.
 * Used when directly setting variation prices through bulk actions.
 *
 * @package mt2Tech\MarkupByAttribute\Backend\Handlers
 */
class PriceSetHandler extends PriceMarkupHandler {
	//region PROPERTIES
	/**
	 * @var	array	Cache for term meta to reduce database queries
	 */
	protected $term_meta_cache;
	//endregion

	/**
	 * Initialize PriceSetHandler with product and markup information.
	 *
	 * @param	string $bulk_action The bulk action being performed
	 * @param	array  $data		The data for price setting
	 * @param	int	$product_id  The ID of the product
	 * @param	array  $variations  List of variation IDs
	 */
	public function __construct($bulk_action, $data, $product_id, $variations) {
		parent::__construct($bulk_action, $product_id, is_numeric($data["value"]) ? (float) $data["value"] : 0);
	}

  /**
	 * Process markup calculations and apply them to variations.
	 * Core method that coordinates the markup calculation workflow.
	 *
	 * @param	string	$bulk_action	The bulk action being performed
	 * @param	array	$data			The pricing data for the operation
	 * @param	int		$product_id		The ID of the product
	 * @param	array	$variations		List of variation IDs
	 */
	public function processProductMarkups($bulk_action, $data, $product_id, $variations) {
		global $mt2mba_utility;
	
		// Handle special case for zero prices
		if ($this->handleZeroPrice($product_id, $this->price_type, $variations)) {
			return;
		}
	
		// Retrieve all attributes and their terms for the product
		$attribute_data = $this->getAttributeData($product_id);
	
		// Build a table of the markup values for the product
		$markup_table = $this->saveProductMarkupMetadata($attribute_data, $product_id);
	
		// Bulk save product markup values
		if ($this->price_type === REGULAR_PRICE) {
			$this->apply_markup_value_updates($markup_table);
		}
	
		// Save new base price and get description
		$rounded_base = round($this->base_price, $this->price_decimals);
		$base_price_description = $this->handleBasePriceUpdate($product_id, $rounded_base);
	
		// Process each variation
		$variation_updates = [];
		foreach ($variations as $variation_id) {
			$variation_updates[] = $this->processVariation(
				$variation_id,
				$markup_table,
				$base_price_description
			);
		}
	
		// Bulk update all variations from the variations_update table
		if (!empty($variation_updates)) {
			$this->updateVariationPricesAndDescriptions($variation_updates);
		}
	}
	
	/**
	 * Build markup table for calculations.
	 * Creates a structured array of markup values and descriptions for each attribute term.
	 *
	 * @param	array	$attribute_data	Array of attributes with labels and terms
	 * @param	int		$product_id		The ID of the product
	 * @return	array					The markup table with calculated values
	 */
	protected function saveProductMarkupMetadata($attribute_data, $product_id) {
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
	
					if (strpos($markup, "%")) {
						// Markup is a percentage
						$markup_value = ($price * floatval($markup)) / 100;
					} else {
						// Markup is a flat amount
						$markup_value = floatval($markup);
					}
	
					// Round markup value based on settings
					$markup_value = MT2MBA_ROUND_MARKUP == "yes" ? 
						round($markup_value, 0) : 
						round($markup_value, $this->price_decimals);
	
					if ($markup_value != 0) {
						$markup_table[$taxonomy][$term->slug] = [
							'term_id' => $term->term_id,
							'markup' => $markup_value,
						];
						
						// Only add description if not ignored and this is regular price
						if (MT2MBA_DESC_BEHAVIOR !== "ignore" && $this->price_type === REGULAR_PRICE) {
							$markup_table[$taxonomy][$term->slug]['description'] = 
								$mt2mba_utility->format_description_markup(
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
	 * Handle special case for zero-price products.
	 * If price is zero and not setting regular price, revert to regular price handling.
	 *
	 * @param	int		$product_id	The ID of the product
	 * @param	string	$price_type	The type of price (regular or sale)
	 * @param	array	$variations	List of variation IDs
	 * @return	bool				True if zero price case was handled
	 */
	private function handleZeroPrice($product_id, $price_type, $variations) {
		if ($this->base_price == 0 && $this->price_type !== REGULAR_PRICE) {
			delete_post_meta($product_id, "mt2mba_base_{$this->price_type}");
			$this->price_type = REGULAR_PRICE;
			$data['value'] = get_metadata("post", $product_id, "mt2mba_base_{$this->price_type}", true);
			$handler = new PriceSetHandler("variable_{$this->price_type}", $data, $product_id, $variations);
			$handler->processProductMarkups($bulk_action, $data, $product_id, $variations);
			return true;
		}
		return false;
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

	/**
	 * Save the base price and generate price description.
	 * Updates metadata and handles transient storage for current base price.
	 *
	 * @param	int		$product_id		The ID of the product
	 * @param	float	$rounded_base	The rounded base price to save
	 * @return	string					Price description or empty string based on settings
	 */
	private function handleBasePriceUpdate($product_id, $rounded_base) {
		update_post_meta($product_id, "mt2mba_base_{$this->price_type}", $rounded_base);
		if ($this->price_type === REGULAR_PRICE) {
			set_transient('mt2mba_current_base_' . $product_id, $rounded_base, HOUR_IN_SECONDS);
		}
		return MT2MBA_HIDE_BASE_PRICE === 'no' ? 
			html_entity_decode(MT2MBA_PRICE_META . $this->base_price_formatted) : '';
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
		$variation = wc_get_product($variation_id);
	  
		if ($this->base_price == 0 && MT2MBA_ALLOW_ZERO === 'yes') {
			return $this->createZeroPriceVariation($variation_id, $variation);
		}

		return $this->calculateVariationPriceAndDescription(
			$variation, 
			$variation_id,
			$markup_table, 
			$base_price_description
		);
	}

	/**
	 * Handle zero-price variation processing.
	 * Creates variation data structure for zero-price cases.
	 *
	 * @param	int			$variation_id	The ID of the variation
	 * @param	WC_Product	$variation		The variation product object
	 * @return	array						Processed zero-price variation data
	 */
	private function createZeroPriceVariation($variation_id, $variation) {
		global $mt2mba_utility;
		$description = "";
		if ($this->price_type === REGULAR_PRICE) {
			$description = $variation->get_description();
			$description = $mt2mba_utility->remove_bracketed_string(
				PRODUCT_MARKUP_DESC_BEG, 
				PRODUCT_MARKUP_DESC_END, 
				$description
			);
		}
		return [
			'id' => $variation_id,
			'price' => 0,
			'description' => trim($description)
		];
	}

	/**
	 * Calculate price and description for a variation.
	 * Applies markups and builds description based on attributes.
	 *
	 * @param	WC_Product	$variation				The variation product object
	 * @param	int			$variation_id			The ID of the variation
	 * @param	array		$markup_table			The markup calculations table
	 * @param	string		$base_price_description	Base price description text
	 * @return	array								Processed variation data with price and description
	 */
	private function calculateVariationPriceAndDescription(
		$variation, 
		$variation_id,
		$markup_table, 
		$base_price_description
	) {
		global $mt2mba_utility;
		$variation_price = $this->base_price;
		$markup_description = '';
	  
		foreach ($variation->get_attributes() as $attribute_id => $term_id) {
			if (isset($markup_table[$attribute_id][$term_id])) {
				$markup = (float) $markup_table[$attribute_id][$term_id]["markup"];
				$variation_price += $markup;
				if (isset($markup_table[$attribute_id][$term_id]["description"])) {
					$markup_description .= PHP_EOL . $markup_table[$attribute_id][$term_id]["description"];
				}
			}
		}

		if ($variation_price < 0) {
			$variation_price = null;
		}

		$description = $this->buildVariationDescription(
			$variation,
			$base_price_description,
			$markup_description,
			$variation_price
		);

		return [
			'id' => $variation_id,
			'price' => $variation_price,
			'description' => trim($description)
		];
	}

	/**
	 * Build variation description with markup information.
	 * Combines existing description with markup details based on settings.
	 *
	 * @param	WC_Product $variation			The variation product object
	 * @param	string	 $base_price_description Base price description text
	 * @param	string	 $markup_description   Markup-specific description text
	 * @param	float	  $variation_price	  The calculated variation price
	 * @return string Complete variation description
	 */
	protected function buildVariationDescription($variation, $base_price_description, $markup_description, $variation_price) {
		global $mt2mba_utility;
		$description = "";
		
		if ($this->price_type === REGULAR_PRICE) {
			// Only modify existing description if not overwriting
			if (MT2MBA_DESC_BEHAVIOR !== "overwrite") {
				$description = $variation->get_description();
				$description = $mt2mba_utility->remove_bracketed_string(
					PRODUCT_MARKUP_DESC_BEG, 
					PRODUCT_MARKUP_DESC_END, 
					$description
				);
			}
	
			// Only add markup description if we have markups and behavior isn't ignore
			if ($markup_description && $variation_price != null && MT2MBA_DESC_BEHAVIOR !== "ignore") {
				$description .= PHP_EOL . PRODUCT_MARKUP_DESC_BEG . 
							  $base_price_description . 
							  $markup_description . 
							  PRODUCT_MARKUP_DESC_END;
			}
		}
		
		return $description;
	}

	/**
	 * Apply markup value updates to the product.
	 *
	 * @param	array	$markup_table	The markup table for the product
	 */
	protected function apply_markup_value_updates($markup_table) {
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
	 * Bulk update variation prices and descriptions in the database.
	 * Uses MySQL's UPSERT functionality for efficient updates.
	 *
	 * @param	array	$variation_updates	Array of variation data to update
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
				$description_updates[] = $wpdb->prepare(
					"(%d, '_variation_description', %s)", 
					$update['id'], 
					$update['description']
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
	
			// Handle descriptions for regular price updates
			if ($this->price_type === REGULAR_PRICE && !empty($description_updates)) {
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
}
?>