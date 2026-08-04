<?php
namespace mt2Tech\MarkupByAttribute\Backend\Handlers;
use mt2Tech\MarkupByAttribute\Utility as Utility;
use Throwable;

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
	//region INITIALIZATION
	/**
	 * Initialize PriceSetHandler with product and markup information
	 *
	 * Extracts the base price from the bulk action data and initializes the parent handler.
	 *
	 * @since 4.0.0
	 * @param string $bulk_action      The bulk action being performed
	 * @param array  $data             The data for price setting (contains 'value' key)
	 * @param int    $product_id       The ID of the product
	 * @param array  $variations       List of variation IDs
	 * @param bool   $owns_transaction False when the caller manages the transaction (see parent property docblock)
	 */
	public function __construct($bulk_action, $data, $product_id, $variations, $owns_transaction = true) {
		// Convert localized decimal input to standardized format using WooCommerce
		$cleaned_value = wc_format_decimal($data["value"], false, true);
		parent::__construct(
			$bulk_action,
			$product_id,
			$variations,
			is_numeric($cleaned_value) ? (float) $cleaned_value : '',
			$owns_transaction
		);
	}
	//endregion

	//region PUBLIC API
	/**
	 * Process markup calculations and apply them to variations
	 *
	 * Core method that coordinates the entire markup calculation workflow:
	 * 1. Validates the base price is not blank (non-numeric prices stop processing)
	 * 2. Retrieves product attributes and builds markup calculation table
	 * 3. Processes each variation to calculate final prices with markups
	 * 4. Bulk updates all variation prices and descriptions in the database
	 *
	 * @since 4.0.0
	 */
	public function processProductMarkups(): void {
		// If the price was blanked out (non-numeric), clean up and stop
		if (!is_numeric($this->base_price)) {
			$this->removeVariationPrices();
			return;
		}

		// Retrieve all attributes and their terms for the product
		$attribute_data = $this->getAttributeData();

		// Build a table of the markup values for the product
		$markup_table = $this->buildMarkupTable($attribute_data);

		// Bulk save product markup values
		if ($this->price_type === MT2MBA_REGULAR_PRICE) {
			$this->bulkSaveProductMarkupValues($markup_table);
		}

		$rounded_base = round($this->base_price, $this->price_decimals);
		$base_price_description = $this->handleBasePriceUpdate($rounded_base);

		// Bulk-fetch variation attributes and descriptions
		list($variation_attributes, $variation_descriptions) = $this->fetchVariationData();

		// Process each variation using pre-fetched data
		$variation_updates = [];
		foreach ($this->variations as $variation_id) {
			$variation_updates[] = $this->processVariation(
				$variation_id,
				$markup_table,
				$base_price_description,
				$variation_attributes[$variation_id] ?? [],
				$variation_descriptions[$variation_id] ?? ''
			);
		}

		// Bulk update all variations from the variations_update table
		if (!empty($variation_updates)) {
			$this->updateVariationPricesAndDescriptions($variation_updates);
		}
	}
	//endregion

	//region VALIDATION & SANITIZATION
	/**
	 * Remove markup artifacts when the base price is cleared
	 *
	 * Cleans up base price metadata and strips markup descriptions from
	 * variation descriptions. Called when the base price is blanked out
	 * (non-numeric) so that no orphaned markup data remains.
	 *
	 * @since 4.0.0
	 */
	public function removeVariationPrices(): void {
		// Remove base price metadata
		delete_post_meta($this->product_id, "mt2mba_base_{$this->price_type}");

		// If clearing the Regular Price, also clean up sale price metadata and descriptions
		if ($this->price_type == MT2MBA_REGULAR_PRICE) {

			// Remove Sales Price metadata
			delete_post_meta($this->product_id, "mt2mba_base_" . MT2MBA_SALE_PRICE);

			// Bulk-fetch all variation descriptions in a single query. A
			// variation-less product reads back nothing and writes nothing.
			$descriptions = BulkMetaIO::fetchMeta($this->variations, '_variation_description');

			// Process descriptions in PHP — strip markup information
			$updates = [];
			foreach ($descriptions as $variation_id => $description) {
				$markup_pos = strpos($description, MT2MBA_PRODUCT_MARKUP_DESC_BEG);

				// If no markup information, skip variation
				if ($markup_pos === false) {
					continue;
				}

				// A description that begins with markup information is left empty;
				// otherwise the markup is cut out of the surrounding text
				$updates[$variation_id] = $markup_pos === 0 ? '' :
					Utility\General::removeBracketedString(
						MT2MBA_PRODUCT_MARKUP_DESC_BEG,
						MT2MBA_PRODUCT_MARKUP_DESC_END,
						$description
					);
			}

			// Clear only the variations actually being rewritten — the read above
			// covered every variation, including ones with nothing to strip
			BulkMetaIO::replaceMeta(array_keys($updates), '_variation_description', $updates);
		}
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
	 * @return array                Markup table indexed by [taxonomy][term_slug] with markup/description data
	 */
	protected function buildMarkupTable($attribute_data): array {
		$markup_table = [];

		foreach ($attribute_data as $taxonomy => $data) {
			$attrb_label = $data['label'];
			foreach ($data['terms'] as $term) {
				$markup = get_term_meta($term->term_id, 'mt2mba_markup', true);

				if (!empty($markup)) {
					// Determine price to calculate markup against based on settings
					if ($this->price_type === MT2MBA_REGULAR_PRICE || MT2MBA_SALE_PRICE_MARKUP === 'yes') {
						$price = $this->base_price;
					} else {
						$price = get_metadata("post", $this->product_id, "mt2mba_base_" . MT2MBA_REGULAR_PRICE, true);
					}

					// Calculate markup value: percentage markups are calculated against the price,
					// fixed markups are used as-is
					if (Utility\General::isPercentage($markup)) {
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
								Utility\General::formatVariationMarkupDescription(
									(string) $markup_value,
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
	 * @param	float	$rounded_base	The rounded base price to save
	 * @return	string					Price description or empty string based on settings
	 */
	private function handleBasePriceUpdate($rounded_base): string {
		// update_post_meta() does not appear to change cached records. Deleting the
		// record before rewriting it appears to be the only way to update the cache.
		delete_post_meta($this->product_id, "mt2mba_base_{$this->price_type}");
		update_post_meta($this->product_id, "mt2mba_base_{$this->price_type}", $rounded_base);
		if ($this->price_type === MT2MBA_REGULAR_PRICE) {
			set_transient('mt2mba_current_base_' . $this->product_id, $rounded_base, HOUR_IN_SECONDS);
		}
		return MT2MBA_HIDE_BASE_PRICE === 'no' ?
			html_entity_decode(MT2MBA_PRICE_META . $this->getRegularPriceForDescription()) . PHP_EOL : '';
	}

	/**
	 * Process a single variation's price and description.
	 * Calculates final price and builds description based on markup table.
	 *
	 * @param	int		$variation_id			The ID of the variation
	 * @param	array	$markup_table			The markup calculations table
	 * @param	string	$base_price_description	Base price description text
	 * @param	array	$attributes				Pre-fetched attribute assignments (taxonomy => term_slug)
	 * @param	string	$current_description	Pre-fetched variation description
	 * @return	array							Processed variation data
	 */
	private function processVariation($variation_id, $markup_table, $base_price_description, $attributes, $current_description): array {
		$variation_price = $this->base_price;
		$markup_description = '';

		foreach ($attributes as $attribute_id => $term_id) {
			if (isset($markup_table[$attribute_id][$term_id])) {
				$markup = (float) $markup_table[$attribute_id][$term_id]["markup"];
				$variation_price += $markup;
				if (isset($markup_table[$attribute_id][$term_id]["description"])) {
					$markup_description .= $markup_table[$attribute_id][$term_id]["description"] . PHP_EOL;
				}
			}
		}

		// Enforce price floor — markups can never produce a negative price
		$variation_price = max(0, $variation_price);

		$description = $this->buildVariationDescription($current_description, $base_price_description, $markup_description, $variation_price);

		return [
			'id'          => $variation_id,
			'price'       => $variation_price,
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
	 * @param	string	$current_description	The variation's current description text
	 * @param	string	$base_price_description	Base price description text (regular price)
	 * @param	string	$markup_description		Markup-specific description text
	 * @param	float	$variation_price		The calculated variation price
	 * @return	string							Complete variation description
	 */
	protected function buildVariationDescription($current_description, $base_price_description, $markup_description, $variation_price): string {
		if ($this->price_type === MT2MBA_REGULAR_PRICE) {
			// Build new description for regular prices and reapply markup operations
			$description = "";

			// Preserve existing non-markup description content unless overwriting
			if (MT2MBA_DESC_BEHAVIOR !== "overwrite") {
				$description = Utility\General::removeBracketedString(
					MT2MBA_PRODUCT_MARKUP_DESC_BEG,
					MT2MBA_PRODUCT_MARKUP_DESC_END,
					$current_description
				);
			}

			// Add separator if description has content
			if (!empty($description)) {
				$description .= PHP_EOL;
			}

			// Add markup information if we have markups and behavior allows it
			if ($markup_description && $variation_price != null && MT2MBA_DESC_BEHAVIOR !== "ignore") {
				$description .= MT2MBA_PRODUCT_MARKUP_DESC_BEG .
							$base_price_description .
							$markup_description .
							MT2MBA_PRODUCT_MARKUP_DESC_END;
			}

			return trim($description);
		} else {
			// For sale prices: preserve existing description to maintain regular price markup consistency
			return $current_description;
		}
	}
	//endregion

	//region DATABASE OPERATIONS
	/**
	 * Bulk-fetch variation attributes and descriptions for all variations.
	 *
	 * @return	array	[attributes, descriptions] lookup arrays keyed by variation ID
	 */
	private function fetchVariationData(): array {
		// All attribute assignments (attribute_pa_color => 'red', etc.)
		$attribute_rows = BulkMetaIO::fetchMetaLike($this->variations, 'attribute_pa_%');

		// All variation descriptions, already keyed by variation ID
		$descriptions = BulkMetaIO::fetchMeta($this->variations, '_variation_description');

		// Organize the attribute rows into a per-variation lookup
		$attributes = [];
		foreach ($attribute_rows as $row) {
			// Strip 'attribute_' prefix to match markup_table keys (e.g., 'pa_color')
			$taxonomy = substr($row->meta_key, 10);
			$attributes[(int) $row->post_id][$taxonomy] = $row->meta_value;
		}

		return [$attributes, $descriptions];
	}

	/**
	 * Apply markup value updates to the product.
	 *
	 * @param	array	$markup_table	The markup table for the product
	 */
	protected function bulkSaveProductMarkupValues($markup_table): void {
		// Delete all existing mt2mba_{term_id}_markup_amount records for this product
		BulkMetaIO::deleteMetaLike(
			$this->product_id,
			BulkMetaIO::likePattern('mt2mba_', '_markup_amount')
		);

		$rows = [];
		foreach ($markup_table as $options) {
			foreach ($options as $details) {
				$rows[] = [
					$this->product_id,
					"mt2mba_{$details['term_id']}_markup_amount",
					number_format(floatval($details['markup']), $this->price_decimals, '.', ''),
				];
			}
		}
		BulkMetaIO::insertMeta($rows);
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
	protected function updateVariationPricesAndDescriptions($updates): void {
		global $wpdb;

		$variation_ids = [];
		$price_rows = [];
		$description_rows = [];

		// Build the row sets for our bulk operations
		foreach ($updates as $update) {
			$variation_ids[] = (int) $update['id'];

			// Reformat price if not null
			if ($update['price'] !== null) {
				$update['price'] = number_format($update['price'], $this->price_decimals, '.', '');
			}

			// Each variation needs both '_price' and price type records
			$price_rows[] = [$update['id'], '_price', $update['price']];
			$price_rows[] = [$update['id'], '_' . $this->price_type, $update['price']];

			if (isset($update['description'])) {
				// Preserve allowed HTML tags (span with id attribute) while sanitizing content
				$allowed_html = ['span' => ['id' => []]];
				$description_rows[(int) $update['id']] = wp_kses($update['description'], $allowed_html);
			}
		}

		// Start transaction for data consistency — but only when no caller owns one
		// already (a nested START TRANSACTION would implicitly COMMIT the outer one)
		if ($this->owns_transaction) {
			$wpdb->query('START TRANSACTION');
		}

		try {
			// Prices: clear both keys for the whole batch, then write them back
			BulkMetaIO::deleteMeta($variation_ids, ['_price', '_' . $this->price_type]);
			BulkMetaIO::insertMeta($price_rows);

			// Descriptions, for both regular and sale price updates
			BulkMetaIO::replaceMeta($variation_ids, '_variation_description', $description_rows);

			if ($this->owns_transaction) {
				$wpdb->query('COMMIT');
			}

		} catch (Throwable $e) {
			if ($this->owns_transaction) {
				$wpdb->query('ROLLBACK');
			}
			// Re-throw so a transaction-owning caller can roll back and report
			throw $e;
		}
	}
	//endregion

	//region UTILITY METHODS
	/**
	 * Get formatted regular price for description display.
	 * Always returns the regular price formatting, regardless of which price type is being set.
	 *
	 * @return	string	Formatted regular price for description
	 */
	private function getRegularPriceForDescription() {
		if ($this->price_type === MT2MBA_REGULAR_PRICE) {
			// We're setting regular price, use the current value being set
			return $this->base_price_formatted;
		} else {
			// We're setting sale price, get stored regular price
			$regular_price = get_metadata("post", $this->product_id, "mt2mba_base_" . MT2MBA_REGULAR_PRICE, true);
			return is_numeric($regular_price) ? strip_tags(wc_price(abs($regular_price))) : '';
		}
	}

	/**
	 * Get attribute data for a product.
	 * Retrieves and formats all taxonomy attribute information.
	 *
	 * @return	array	Formatted attribute data with labels and terms
	 */
	private function getAttributeData(): array {
		$attribute_data = [];
		foreach (wc_get_product($this->product_id)->get_attributes() as $pa_attrb) {
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