<?php
namespace mt2Tech\MarkupByAttribute\Backend;
//use mt2Tech\MarkupByAttribute\Backend\Handlers;
use mt2Tech\MarkupByAttribute\Utility as Utility;

/**
 * Main class for handling product backend actions.
 * Manages hooks into WordPress and WooCommerce for markup application.
 *
 * @package mt2Tech\MarkupByAttribute\Backend
 */
class Product {
	//region INITIALIZATION
	/**
	 * Initialize Product class and set up action hooks.
	 * Sets up WooCommerce integration and registers necessary handlers.
	 */
	public function __construct() {
		// Override WooCommerce's default maximum variation threshold with custom setting
		if (!defined("WC_MAX_LINKED_VARIATIONS")) {
			define("WC_MAX_LINKED_VARIATIONS", MT2MBA_MAX_VARIATIONS);
		}

		// Add JavaScript for markup reapplication functionality on product edit pages
		add_action('admin_enqueue_scripts', [$this, 'enqueueMarkupScripts']);

		// Hook into WooCommerce's bulk variation editing to apply markups during price changes
		add_action("woocommerce_bulk_edit_variations", [$this, "handleBulkPriceAction"], 10, 4);

		// Add base price fields to product general options panel
		add_action('woocommerce_product_options_general_product_data', [$this, 'addBasePriceFields']);

		// Handle AJAX requests to reapply markups to variations
		add_action('wp_ajax_handleMarkupReapplication', [$this, 'handleMarkupReapplication']);

		// Handle AJAX requests to get formatted base price for confirmation messages
		add_action('wp_ajax_getFormattedBasePrice', [$this, 'getFormattedBasePrice']);

		// Handle AJAX requests to refresh general panel after price changes
		add_action('wp_ajax_mt2mba_refresh_general_panel', [$this, 'refreshProductGeneralPanel']);
	}

	/**
	 * Enqueue markup reapplication JavaScript.
	 * Sets up necessary scripts and localization data.
	 *
	 * @param	string	$hook	The current admin page hook
	 */
	public function enqueueMarkupScripts($hook) {
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

			// Get base price in store currency format
			$base_price = get_post_meta($product->get_id(), 'mt2mba_base_regular_price', true);
			$formatted_price = strip_tags(wc_price($base_price));

			wp_localize_script(
				'mt2mba-reapply-markup',
				'mt2mbaLocal',
				array(
					'ajaxUrl' => admin_url('admin-ajax.php'),
					'security' => wp_create_nonce('handleMarkupReapplication'),
					'variationsNonce' => wp_create_nonce('load-variations'),
					'i18n' => array(
						'reapplyMarkupss' => __('Reapply markups to prices', 'markup-by-attribute-for-woocommerce'),
						'confirmReapply' => __('Reprice variations at %s, plus or minus the markups?', 'markup-by-attribute-for-woocommerce'),
						'failedRecalculating' => __('Failed to reapply markups. Please try again.', 'markup-by-attribute-for-woocommerce')
					)
				)
			);
		}
	}
	//endregion

	//region AJAX HANDLERS
	/**
	 * Handle AJAX markup reapplication request
	 *
	 * Processes bulk reapplication of markups to all product variations. Uses database
	 * transactions to ensure data consistency and includes comprehensive error handling.
	 * Cleans up WooCommerce caches and transients after successful completion.
	 *
	 * @since 4.0.0
	 */
	public function handleMarkupReapplication() {
		try {
			// Validate request and get product info
			$validation_result = $this->validateReapplyMarkupsRequest();
			if (!$validation_result) {
				return; // Error response already sent by validation method
			}

			$product_id = $validation_result['product_id'];
			$product = $validation_result['product'];

			// Start database transaction to ensure data consistency
			global $wpdb;
			$wpdb->query('START TRANSACTION');

			try {
				// Process all product variations with markup recalculation
				$variations = $product->get_children();
				if (!empty($variations)) {
					$this->processVariationsWithMarkup($product_id, $variations);
				}

				// Commit transaction only after all operations succeed
				$wpdb->query('COMMIT');

				// Clean up WordPress and WooCommerce caches for updated data
				$this->cleanupCachesAndTransients($product_id, $variations);

				wp_send_json_success(['completed' => true]);

			} catch (Exception $e) {
				// Rollback transaction on any error to maintain data integrity
				$wpdb->query('ROLLBACK');
				throw $e;
			}

		} catch (Exception $e) {
			wp_send_json_error(['message' => $e->getMessage()]);
		}
	}

	/**
	 * Get formatted base price for confirmation messages
	 *
	 * Retrieves the current base price from transient cache (for performance) with
	 * fallback to stored metadata. Returns formatted price for JavaScript confirmation dialogs.
	 *
	 * @since 4.0.0
	 */
	public function getFormattedBasePrice() {
		check_ajax_referer('handleMarkupReapplication', 'security');

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
		if (!$product_id) {
			wp_send_json_error();
			return;
		}

		// Check transient cache first for performance (set during price operations)
		$base_price = get_transient('mt2mba_current_base_' . $product_id);
		if ($base_price === false) {
			// Fall back to stored metadata if transient expired
			$base_price = get_post_meta($product_id, 'mt2mba_base_regular_price', true);
		}

		wp_send_json_success([
			'formatted_price' => html_entity_decode(strip_tags(wc_price($base_price)))
		]);
	}

	/**
	 * Handle AJAX request to refresh general panel content
	 *
	 * Updates the product general panel display after price changes. Uses WordPress's
	 * postdata system to temporarily set up the product context, then captures the
	 * output of the general panel hooks for return via AJAX.
	 *
	 * @since 4.3.0
	 */
	public function refreshProductGeneralPanel() {
		check_ajax_referer('handleMarkupReapplication', 'security');

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

		if (!$product_id) {
			wp_send_json_error();
			return;
		}

		// Set up the global $post object to provide proper context for hooks
		global $post;
		$post = get_post($product_id);
		setup_postdata($post);

		// Capture the output of all general panel hooks using output buffering
		ob_start();
		do_action('woocommerce_product_options_general_product_data');
		$html = ob_get_clean();

		// Clean up global state to prevent side effects
		wp_reset_postdata();

		wp_send_json_success(['html' => $html]);
	}
	//endregion

	//region BULK OPERATIONS
	/**
	 * Handle bulk edit variations and apply markups.
	 * Processes different types of bulk price actions.
	 *
	 * @param	string	$bulk_action	The bulk action being performed
	 * @param	array	$data			Values from JavaScript popup
	 * @param	int		$product_id		The ID of the product
	 * @param	array	$variations		List of variation IDs
	 */
	public function handleBulkPriceAction($bulk_action, $data, $product_id, $variations) {
		// Determine which class should extend PriceMarkupHandler based on the bulk_action
		if ($bulk_action == "variable_regular_price" || $bulk_action == "variable_sale_price") {
			// Set either the regular price or the sale price
			$handler = new Handlers\PriceSetHandler($bulk_action, $data, $product_id, $variations);

		} elseif (strpos($bulk_action, "_price_increase") || strpos($bulk_action, "_price_decrease")) {
			// Increase or decrease the regular price or the sale price
			$handler = new Handlers\PriceUpdateHandler($bulk_action, $data, $product_id, $variations);

		} elseif ($bulk_action == "delete_all") {
			// Delete all markup metadata for product
			$handler = new Handlers\MarkupDeleteHandler("", [], $product_id, []);

		} else {
			// If none of the above, leave and don't execute $handler
			return;
		}

		// Invoke the processProductMarkups() function from the class that was decided above
		$handler->processProductMarkups((string) $bulk_action, (array) $data, (string) $product_id, (array) $variations);
	}
	//endregion

	//region UI DISPLAY
	/**
	 * Display base price fields in product general options panel.
	 * Shows readonly fields for base regular and sale prices.
	 */
	public function addBasePriceFields() {
		global $post;

		if ($post) {
			$base_regular_price = get_post_meta($post->ID, 'mt2mba_base_regular_price', true);
			$base_sale_price = get_post_meta($post->ID, 'mt2mba_base_sale_price', true);
			$currency_symbol = " (" . get_woocommerce_currency_symbol() . ")";

			echo '<div class="options_group show_if_variable">';

			// Regular Price Field
			woocommerce_wp_text_input([
				'id'				=> 'base_regular_price',
				'label'				=> __('Regular base price', 'markup-by-attribute-for-woocommerce') . $currency_symbol,
				'description'		=> __('Regular base price for the variations before markup', 'markup-by-attribute-for-woocommerce'),
				'value'				=> $base_regular_price,
				'type'				=> 'text',
				'desc_tip'			=> true,
				'class'				=> 'wc_input_price',
				'data_type'			=> 'price',
				'custom_attributes'	=> ['readonly' => 'readonly']
			]);

			// Sale Price Field (if exists)
			if ($base_sale_price !== '') {
				woocommerce_wp_text_input([
					'id'			=> 'base_sale_price',
					'label'				=> __('Sale base price', 'markup-by-attribute-for-woocommerce') . $currency_symbol,
					'description'		=> __('Sale base price for the variations before markup', 'markup-by-attribute-for-woocommerce'),
					'value'			=> $base_sale_price,
					'type'			=> 'text',
					'desc_tip'		=> true,
					'class'			=> 'wc_input_price',
					'data_type'		=> 'price',
					'custom_attributes'	=> ['readonly' => 'readonly']
				]);
			}

			echo '<div id="base_price_info"><p class="form-field">' .
				'<span class="base-price-info dashicons dashicons-info"></span>' .
				__('Change base prices with the <em>Bulk actions</em> on the <b>Variations</b> tab.', 'markup-by-attribute-for-woocommerce') .
				'</p></div>';
			echo '</div>';
		}
	}
	//endregion

	//region PRIVATE UTILITIES
	/**
	 * Validate reapplication request parameters
	 *
	 * Performs comprehensive security validation including nonce verification,
	 * capability checks, and product type validation. Returns product data
	 * on success or sends JSON error response on failure.
	 *
	 * @since 4.0.0
	 * @return array|false Product data array on success, false on failure
	 */
	private function validateReapplyMarkupsRequest() {
		if (!check_ajax_referer('handleMarkupReapplication', 'security', false)) {
			wp_send_json_error(['message' => __('Permission denied', 'markup-by-attribute-for-woocommerce')]);
			return false;
		}

		if (!current_user_can('edit_products')) {
			wp_send_json_error(['message' => __('Permission denied', 'markup-by-attribute-for-woocommerce')]);
			return false;
		}

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
		if (!$product_id || !($product = wc_get_product($product_id)) || !$product->is_type('variable')) {
			wp_send_json_error(['message' => __('Invalid product ID', 'markup-by-attribute-for-woocommerce')]);
			return false;
		}

		return ['product_id' => $product_id, 'product' => $product];
	}

	/**
	 * Process variations with markup reapplication.
	 * Applies markup calculations to regular and sale prices.
	 *
	 * @param	int		$product_id	The ID of the product
	 * @param	array	$variations	List of variation IDs
	 */
	private function processVariationsWithMarkup($product_id, $variations) {
		$base_regular_price = get_post_meta($product_id, 'mt2mba_base_regular_price', true);
		$data = ['value' => $base_regular_price];
		$handler = new Handlers\PriceSetHandler('variable_regular_price', $data, $product_id, $variations);
		$handler->processProductMarkups('variable_regular_price', $data, $product_id, $variations);

		$base_sale_price = get_post_meta($product_id, 'mt2mba_base_sale_price', true);
		if (!empty($base_sale_price)) {
			$data = ['value' => $base_sale_price];
			$handler = new Handlers\PriceSetHandler('variable_sale_price', $data, $product_id, $variations);
			$handler->processProductMarkups('variable_sale_price', $data, $product_id, $variations);
		}
	}

	/**
	 * Clean up caches and transients after updates.
	 * Ensures proper cache invalidation for updated data.
	 *
	 * @param	int		$product_id	The ID of the product
	 * @param	array	$variations	List of variation IDs
	 */
	private function cleanupCachesAndTransients($product_id, $variations) {
		// Clear WordPress cache
		wp_cache_flush();
		clean_post_cache($product_id);

		// Clear WooCommerce specific caches
		wc_delete_product_transients($product_id);
		if (!empty($variations)) {
			foreach ($variations as $variation_id) {
				clean_post_cache($variation_id);
				wc_delete_product_transients($variation_id);
			}
		}

		// Clear variable product price cache
		delete_transient('wc_var_prices_' . $product_id);

		// Delete WooCommerce's variation parent price meta
		delete_post_meta($product_id, '_price');
		delete_post_meta($product_id, '_min_variation_price');
		delete_post_meta($product_id, '_max_variation_price');
		delete_post_meta($product_id, '_min_variation_regular_price');
		delete_post_meta($product_id, '_max_variation_regular_price');
		delete_post_meta($product_id, '_min_variation_sale_price');
		delete_post_meta($product_id, '_max_variation_sale_price');

		// Force WooCommerce to recalculate prices
		if (class_exists('\WC_Product_Variable')) {
			\WC_Product_Variable::sync($product_id);
		}
	}
	//endregion

}
?>