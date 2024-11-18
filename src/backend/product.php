<?php
namespace mt2Tech\MarkupByAttribute\Backend;
use mt2Tech\MarkupByAttribute\Utility as Utility;
/**
 * PriceMarkupHandler creates an abstract shell class with basic Markup-by-Attribute
 * product variation functions. It is extended by the appropriate classes depending on which
 * bulk editing functions are being invoked.
 */
abstract class PriceMarkupHandler {
	/** @var	string	The type of price (regular or sale) */
	protected $price_type;
	/** @var	int		The ID of the product being processed */
	protected $product_id;
	/** @var	float	The base price of the product */
	protected $base_price;
	/** @var	string	The base price formatted according to the store's currency settings */
	protected $base_price_formatted;
	/** @var	int		The number of decimal places to use for price calculations */
	protected $price_decimals;

	/**
	 * Constructor for the PriceMarkupHandler class.
	 *
	 * @param	string	$bulk_action	The bulk action being performed
	 * @param	int		$product_id		The ID of the product being processed
	 * @param	float	$base_price		The base price of the product
	 */
	public function __construct($bulk_action, $product_id, $base_price) {
		
		// Create 'regular_price'	string	in one place
		if (!defined('REGULAR_PRICE')) {
			define('REGULAR_PRICE', 'regular_price');
		}

		// Extract price_type from bulk_action
		if ($bulk_action) {
			$bulk_action_array = explode("_", $bulk_action);
			$this->price_type = $bulk_action_array[1] . "_" . $bulk_action_array[2];
		}

		$this->product_id = $product_id;
		$this->base_price = $base_price;
		$this->base_price_formatted = strip_tags(wc_price(abs($this->base_price)));
		$this->price_decimals = wc_get_price_decimals();
	}

	/**
	 * Apply markup to product price. This method must be implemented by child classes.
	 *
	 * @param	string	$price_type The type of price (regular or sale)
	 * @param	array	$data		The data for the markup operation
	 * @param	int		$product_id The ID of the product
	 * @param	array	$variations List of product variations
	 */
	abstract public function applyMarkup($price_type, $data, $product_id, $variations);
}

/**
 * Concrete class for handling product price setting, which extends PriceMarkupHandler
 * and overrides its abstract methods.
 */
class PriceSetHandler extends PriceMarkupHandler {
	/** @var	array Cache for term meta to reduce database queries */
	protected $term_meta_cache = [];

	/**
	 * Constructor for the PriceSetHandler class.
	 *
	 * @param	string	$bulk_action	The bulk action being performed
	 * @param	array	$data			The data for the price setting operation
	 * @param	int		$product_id		The ID of the product being processed
	 * @param	array	$variations		List of product variations
	 */
	public function __construct($bulk_action, $data, $product_id, $variations) {
		parent::__construct($bulk_action, $product_id, is_numeric($data["value"]) ? (float) $data["value"] : 0);
	}

	/**
	 * Build a table of markup values for the product.
	 *
	 * @param	array	$terms		The terms associated with the product
	 * @param	int		$product_id	The ID of the product
	 * @return	array				The markup table
	 */
	protected function build_markup_table($terms, $product_id) {
		global $mt2mba_utility, $wpdb;
		$markup_table = [];

		// Fetch all term meta in a single query
		$term_ids = wp_list_pluck($terms, 'term_id');
		$term_meta = $wpdb->get_results($wpdb->prepare(
			"SELECT term_id, meta_key, meta_value FROM {$wpdb->termmeta}
			WHERE term_id IN (" . implode(',', array_fill(0, count($term_ids), '%d')) . ")
			AND meta_key = 'mt2mba_markup'",
			$term_ids
		), ARRAY_A);

		// Populate term_meta_cache
		$this->term_meta_cache = [];
		foreach ($term_meta as $meta) {
			$this->term_meta_cache[$meta['term_id']]['mt2mba_markup'] = $meta['meta_value'];
		}

		// Calculate markup for each term for this product
		foreach ($terms as $term) {
			$meta_key = "mt2mba_{$term->term_id}_markup_amount";
			$markup = isset($this->term_meta_cache[$term->term_id]['mt2mba_markup']) ?
				$this->term_meta_cache[$term->term_id]['mt2mba_markup'] :
				null;
			// Set price to calculate markup against
			if ($this->price_type === REGULAR_PRICE || MT2MBA_SALE_PRICE_MARKUP === 'yes') {
				$price = $this->base_price;
			} else {
				$price = get_metadata("post", $product_id, "mt2mba_base_" . REGULAR_PRICE, true);
			}

			if (!empty($markup)) {
				if (strpos($markup, "%")) {
					// Markup is a percentage
					$markup_value = ($price * floatval($markup)) / 100;
				} else {
					// Markup is a flat amount
					$markup_value = floatval($markup);
				}

				// Round markup value
				$markup_value = MT2MBA_ROUND_MARKUP == "yes" ? round($markup_value, 0) : round($markup_value, $this->price_decimals);

				if ($markup_value != 0) {
					$markup_table[$term->taxonomy][$term->slug]['term_id'] = $term->term_id;
					$markup_table[$term->taxonomy][$term->slug]['markup'] = $markup_value;
					if (MT2MBA_DESC_BEHAVIOR !== "ignore" && $this->price_type === REGULAR_PRICE) {
						$markup_table[$term->taxonomy][$term->slug]['description'] = $mt2mba_utility->format_description_markup($markup_value, $term->name);
					}
				}
			}
		}
		return $markup_table;
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
	 * Perform bulk update of variation prices and descriptions.
	 * Uses MySQL's handling of duplicate keys to effectively perform an UPSERT operation.
	 * When inserting a duplicate (post_id, meta_key) pair, MySQL will update the existing value.
	 *
	 * @param array $updates Array of updates to apply. Each element contains:
	 *                      - id:          (int)    Variation ID
	 *                      - price:       (float)  New price value
	 *                      - description: (string) New variation description
	 */
	protected function bulk_variation_update($updates) {
		global $wpdb;

		$variation_ids = [];
		$price_inserts = [];
		$description_updates = [];

		// Build arrays for our SQL operations
		foreach ($updates as $update) {
			$variation_ids[] = (int)$update['id'];
			
			// Each variation needs both '_price' and price type (e.g., '_regular_price') records
			$price_inserts[] = $wpdb->prepare(
				"(%d, '_price', %01.{$this->price_decimals}f),
				(%d, '_{$this->price_type}', %01.{$this->price_decimals}f)",
				$update['id'], 
				$update['price'],
				$update['id'], 
				$update['price']
			);

			// Build description updates if needed
			$description_updates[] = $wpdb->prepare(
				"(%d, '_variation_description', %s)", 
				$update['id'], 
				$update['description']
			);
		}

		// Start transaction for data consistency
		$wpdb->query('START TRANSACTION');

		try {
			// Insert/update prices - MySQL handles duplicate (post_id, meta_key) pairs
			// by updating the existing values
			if (!empty($price_inserts)) {
				$wpdb->query("
					INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
					VALUES " . implode(",\n", $price_inserts)
				);
			}

			// Only handle descriptions for regular price updates
			if ($this->price_type === REGULAR_PRICE) {
				// Remove existing descriptions
				$wpdb->query($wpdb->prepare(
					"DELETE FROM {$wpdb->postmeta}
					WHERE post_id IN (" . implode(',', array_fill(0, count($variation_ids), '%d')) . ")
					AND meta_key = '_variation_description'",
					$variation_ids
				));

				// Insert new descriptions if we have any
				if (!empty($description_updates)) {
					$wpdb->query("
						INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value)
						VALUES " . implode(', ', $description_updates)
					);
				}
			}

			$wpdb->query('COMMIT');

		} catch (Exception $e) {
			$wpdb->query('ROLLBACK');
			error_log('Bulk variation update failed: ' . $e->getMessage());
			throw $e;
		}
	}

	/**
	 * Main function to apply markup to product variations.
	 *
	 * @param	string	$bulk_action	The bulk action being performed
	 * @param	array	$data			The data for the markup operation
	 * @param	int		$product_id		The ID of the product
	 * @param	array	$variations		List of product variations
	 */
	public function applyMarkup($bulk_action, $data, $product_id, $variations) {
		global $mt2mba_utility;

		// If setting sale price to zero...
		if ($this->base_price == 0 && $this->price_type !== REGULAR_PRICE) {
			delete_post_meta($product_id, "mt2mba_base_{$this->price_type}");
			$this->price_type = REGULAR_PRICE;
			$data['value'] = get_metadata("post", $product_id, "mt2mba_base_{$this->price_type}", true);
			$handler = new PriceSetHandler("variable_{$this->price_type}", $data, $product_id, $variations);
			$handler->applyMarkup($bulk_action, $data, $product_id, $variations);
			return;
		}

		// Retrieve all attributes for the product
		$all_terms = [];
		foreach (wc_get_product($product_id)->get_attributes() as $pa_attrb) {
			if ($pa_attrb->is_taxonomy()) {
				$taxonomy = $pa_attrb->get_name();
				$terms = get_terms(["taxonomy" => $taxonomy, "hide_empty" => false]);
				$all_terms = array_merge($all_terms, $terms);
			}
		}

		// Build a table of the markup values for the product
		$markup_table = $this->build_markup_table($all_terms, $product_id);

		// Bulk save product markup values
		if ($this->price_type === REGULAR_PRICE) {
			$this->apply_markup_value_updates($markup_table);
		}

		// Save new base price
		update_post_meta($product_id, "mt2mba_base_{$this->price_type}", round($this->base_price, $this->price_decimals));

		// Format the base price description for the variations
		$base_price_description = MT2MBA_HIDE_BASE_PRICE === 'no' ? html_entity_decode(MT2MBA_PRICE_META . $this->base_price_formatted) : '';

		// Set up table with variation prices
		$variation_updates = [];
		foreach ($variations as $variation_id) {
			$variation = wc_get_product($variation_id);
			$variation_price = $this->base_price;
			$markup_description = '';
			$attributes = $variation->get_attributes();
			foreach ($attributes as $attribute_id => $term_id) {
				if (isset($markup_table[$attribute_id][$term_id])) {
					$markup = (float) $markup_table[$attribute_id][$term_id]["markup"];
					$variation_price += $markup;
					if (isset($markup_table[$attribute_id][$term_id]["description"])) {
						$markup_description .= PHP_EOL . $markup_table[$attribute_id][$term_id]["description"];
					}
				}
			}

			$variation_price = max($variation_price, 0);

			$description = "";
			if ($this->price_type === REGULAR_PRICE) {
				if (MT2MBA_DESC_BEHAVIOR !== "overwrite") {
					$description = $variation->get_description();
					$description = $mt2mba_utility->remove_bracketed_string(PRODUCT_MARKUP_DESC_BEG, PRODUCT_MARKUP_DESC_END, $description);
				}

				if ($markup_description) {
					$description .= PHP_EOL . PRODUCT_MARKUP_DESC_BEG . $base_price_description . $markup_description . PRODUCT_MARKUP_DESC_END;
				}
			}

			$variation_updates[] = [
				'id' => $variation_id,
				'price' => $variation_price,
				'description' => trim($description)
			];
		}

		// Bulk update all variations from the variations_update table
		if (!empty($variation_updates)) {
			// Bulk update the variation prices and descriptions
			$this->bulk_variation_update($variation_updates);
		}
	}
}

/**
 * Concrete class for handling product price increase/decrease, which extends
 * PriceMarkupHandler and overrides its abstract methods.
 */
class PriceUpdateHandler extends PriceMarkupHandler {
	/**
	 * PriceUpdateHandler constructor.
	 * @param	string	$bulk_action	The selection from the variation bulk actions menu.
	 * @param	array	$data			Values passed in from JScript pop-up.
	 * @param	string	$product_id		ID of the variable product.
	 * @param	array	$variations		List of variation IDs for the variable product.
	 */
	public function __construct($bulk_action, $data, $product_id, $variations) {
		parent::__construct($bulk_action, $product_id, is_numeric($data["value"]) ? (float) $data["value"] : 0);
	}

	/**
	 * reapply base price based on bulk action and markup.
	 * @param	string	$bulk_action	The selection from the variation bulk actions menu.
	 * @param	string	$markup			The amount or percentage to increase or decrease by.
	 * @param	float	$base_price		The original base price that we are changing.
	 * @return	float					The new base price (before markup).
	 */
	private function recalc_base_price($bulk_action, $markup, $base_price) {
		// Indicate whether we are increasing or decreasing
		$signed_data = strpos($bulk_action, "decrease") ? 0 - floatval($markup) : floatval($markup);

		// Calc based on whether it is a percentage or fixed number
		if (strpos($markup, "%")) {
			return $base_price + ($base_price * $signed_data) / 100;
		} else {
			return $base_price + $signed_data;
		}
	}

	/**
	 * Increase or decrease product price and apply markup.
	 * @param	string	$bulk_action	The selection from the variation bulk actions menu.
	 * @param	array	$data			Values passed in from JScript pop-up.
	 * @param	string	$product_id		ID of the variable product.
	 * @param	array	$variations 	List of variation IDs for the variable product.
	 */
	public function applyMarkup($bulk_action, $data, $product_id, $variations) {
		// If base price metadata is present, that means the product contains variables with attribute pricing.
		$base_price = get_metadata("post", $product_id, "mt2mba_base_{$this->price_type}", true);
		if ($base_price) {
			// reapply a new base price according to the bulk action.
			// Bulk action could be any of
			//	 * variable_regular_price_increase
			//	 * variable_regular_price_decrease
			//	 * variable_sale_price_increase
			//	 * variable_sale_price_decrease
			$new_data = [];
			$new_data["value"] = $this->recalc_base_price($bulk_action, $data["value"], $base_price);
			// And then loop back through changing the bulk action type to one of the two 'set price' options.
			// This will reset the prices on all variations to the new base regular/sale price plus the
			// attribute markup.
			//	 * variable_regular_price
			//	 * variable_sale_price
			$handler = new PriceSetHandler("variable_{$this->price_type}", $new_data, $product_id, $variations);
			$handler->applyMarkup($bulk_action, $data, $product_id, $variations);
		}
	}
}

/**
 * Concrete class for handling product markup deletion, which extends
 * PriceMarkupHandler and overrides its abstract methods.
 */
class MarkupDeleteHandler extends PriceMarkupHandler {
	/**
	 * MarkupDeleteHandler constructor. Does nothing (required to prevent parent::__construct() from firing).
	 * @param	string	$var1		Empty string to satisfy $handler->applyMarkup().
	 * @param	string	$var2		Empty string to satisfy $handler->applyMarkup().
	 * @param	string	$product_id	The product whose metadata is to be deleted.
	 * @param	array	$var4		Empty array to satisfy $handler->applyMarkup().
	 */
	public function __construct($var1, $var2, $product_id, $var4) {
		// Nothing here (required to prevent parent::__construct() from firing)
	}

	/**
	 * Delete all Markup-by-Attribute metadata for product whose variations are deleted
	 * @param	string	$var1		Empty string to satisfy $handler->applyMarkup().
	 * @param	string	$var2		Empty string to satisfy $handler->applyMarkup().
	 * @param	string	$product_id The product whose metadata is to be deleted.
	 * @param	array	$var4		Empty array to satisfy $handler->applyMarkup().
	 */
	public function applyMarkup($var1, $var2, $product_id, $var4) {
		// Delete all Markup-by-Attribute metadata for product
		global $wpdb;
		$wpdb->query(
			"DELETE FROM {$wpdb->postmeta} WHERE post_id = '{$product_id}' AND meta_key LIKE 'mt2mba_%'"
		);
	}
}

/**
 * Main class for handling product backend actions, such as hooking into WordPress and WooCommerce
 * to apply markup to product prices based on various bulk actions.
 */
class Product {
	/**
	 * Initialization method visible before instantiation.
	 */
	public function __construct() {
		// Override the max variation threshold with value from settings
		if (!defined("WC_MAX_LINKED_VARIATIONS")) {
			define("WC_MAX_LINKED_VARIATIONS", MT2MBA_MAX_VARIATIONS);
		}

		// Hook mt2mba markup code into bulk actions
		add_action("woocommerce_bulk_edit_variations", [$this, "mt2mba_apply_markup_to_price"], 10, 4);

		// Add action to enqueue reapply markup JavaScript
		add_action('admin_enqueue_scripts', [$this, 'ajax_enqueue_reapply_markups_js']);
	
		// Add AJAX handlers for reapply markup
		add_action('wp_ajax_mt2mba_reapply_markup', [$this, 'ajax_handle_reapply_markup'], 10, 1);
	}

	/**
	 * Enqueue the reapply markup JavaScript file and required dependencies.
	 * Sets up all necessary localization data including security nonces for both
	 * our custom markup recalculation and WooCommerce's variation loading.
	 *
	 * @param string $hook The current admin page hook
	 */
	public function ajax_enqueue_reapply_markups_js($hook) {
		// Only load on product edit page
		if (!in_array($hook, ['post.php', 'post-new.php'])) {
			return;
		}
		
		// Only load for product post type
		if (get_post_type() !== 'product') {
			return;
		}
		
		// Get the product
		$product = wc_get_product(get_the_ID());
		
		// Only load for variable products
		if ($product && $product->is_type('variable')) {
			wp_enqueue_script(
				'mt2mba-reapply-markup',
				plugins_url('js/jq-mt2mba-reapply-markups-product.js', dirname(__FILE__)),
				['jquery', 'wc-admin-variation-meta-boxes'],
				MT2MBA_VERSION,
				true
			);

			// Localize the script with all required data
			wp_localize_script(
				'mt2mba-reapply-markup',
				'mt2mbaLocal',
				array(
					'ajaxUrl' => admin_url('admin-ajax.php'),
					'productId' => get_the_ID(),
					'security' => wp_create_nonce('mt2mba_reapply_markup'),
					'variationsNonce' => wp_create_nonce('load-variations'),
					'i18n' => array(
						'failedRecalculating' => __('Failed to reapply markups. Please try again.', 'markup-by-attribute'),
						'reapplyMarkups' => __('Reapply markups to prices', 'markup-by-attribute')
					)
				)
			);
		}
	}

	/**
	 * Handle the AJAX request to reapply markup
	 * 
	 * @param int $product_id Optional product ID for bulk operations
	 */
	public function ajax_handle_reapply_markup() {
		// Verify nonce first
		if (!check_ajax_referer('mt2mba_reapply_markup', 'security', false)) {
			wp_send_json_error(['message' => __('Permission denied', 'markup-by-attribute')]);
			return;
		}
		
		if (!current_user_can('edit_products')) {
			wp_send_json_error(['message' => __('Permission denied', 'markup-by-attribute')]);
			return;
		}

		// Obtain product ID
		if (isset($_POST['product_id'])) {
			$product_id = absint($_POST['product_id']);
		} else {
			wp_send_json_error(['message' => __('Invalid product ID', 'markup-by-attribute')]);
			return;
		}

		if (!$product_id) {
			wp_send_json_error(['message' => __('Invalid product ID', 'markup-by-attribute')]);
			return;
		}
		
		try {
			$product = wc_get_product($product_id);
			if (!$product || $product->get_type() !== 'variable') {
				throw new Exception('Invalid product type');
			}
			
			// Get the base regular price
			$base_price = get_post_meta($product_id, 'mt2mba_base_regular_price', true);
			if (!$base_price) {
				throw new Exception('No base price found');
			}
			
			// Get all variations
			$variations = $product->get_children();

			// Create data array for PriceSetHandler
			$data = ['value' => $base_price];
			
			// Use existing PriceSetHandler to reapply prices
			$handler = new PriceSetHandler('variable_regular_price', $data, $product_id, $variations);
			
			// Make sure we complete all database operations
			global $wpdb;
			$wpdb->query('START TRANSACTION');
			
			try {
				$handler->applyMarkup('variable_regular_price', $data, $product_id, $variations);
				
				// Force all pending database operations to complete
				$wpdb->query('COMMIT');
				
				// Clear all caches
				wp_cache_flush();
				clean_post_cache($product_id);
				foreach ($variations as $variation_id) {
					clean_post_cache($variation_id);
				}
				
				wp_send_json_success([
					'completed' => true,
					'product_id' => $product_id,
					'variations_count' => count($variations)
				]);
				
			} catch (Exception $e) {
				$wpdb->query('ROLLBACK');
				throw $e;
			}
		} catch (Exception $e) {
			wp_send_json_error([
				'message' => $e->getMessage(),
				'product_id' => $product_id
			]);
		}
	}

	/**
	 * Hook into woocommerce_bulk_edit_variations and adjust price after setting new one.
	 * @param	string	$bulk_action	The selection from the variation bulk actions menu.
	 * @param	array	$data			Values passed in from JScript pop-up.
	 * @param	string	$product_id		ID of the variable product.
	 * @param	array	$variations		List of variation IDs for the variable product.
	 */
	public function mt2mba_apply_markup_to_price($bulk_action, $data, $product_id, $variations) {
		// Determine which class should extend PriceMarkupHandler based on the bulk_action
		if ($bulk_action == "variable_regular_price" || $bulk_action == "variable_sale_price") {
			// Set either the regular price or the sale price
			$handler = new PriceSetHandler($bulk_action, $data, $product_id, $variations);

		} elseif (strpos($bulk_action, "_price_increase") || strpos($bulk_action, "_price_decrease")) {
			// Increase or decrease the regular price or the sale price
			$handler = new PriceUpdateHandler($bulk_action, $data, $product_id, $variations);

		} elseif ($bulk_action == "delete_all") {
			// Delete all markup metadata for product
			$handler = new MarkupDeleteHandler("", [], $product_id, []);

		} else {
			// If none of the above, leave and don't execute $handler
			return;
		}

		// Invoke the applyMarkup() function from the class that was decided above
		$handler->applyMarkup((string) $bulk_action, (array) $data, (string) $product_id, (array) $variations);
	}
}
?>