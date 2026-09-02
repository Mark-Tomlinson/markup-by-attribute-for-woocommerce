<?php
namespace mt2Tech\MarkupByAttribute\Backend;
//use mt2Tech\MarkupByAttribute\Backend\Handlers;
use Throwable;

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
		if (!defined('WC_MAX_LINKED_VARIATIONS')) {
			define('WC_MAX_LINKED_VARIATIONS', MT2MBA_MAX_VARIATIONS);
		}

		// Add JavaScript for markup reapplication functionality on product edit pages
		add_action('admin_enqueue_scripts', [$this, 'enqueueMarkupScripts']);

		// Hook into WooCommerce's bulk variation editing to apply markups during price changes
		add_action('woocommerce_bulk_edit_variations', [$this, 'handleBulkPriceAction'], 10, 4);

		// Add base price fields to product general options panel
		add_action('woocommerce_product_options_general_product_data', [$this, 'addBasePriceFields']);

		// Warn about markups the cart can never charge, directly above the
		// variations toolbar where the repricing actions live
		add_action('woocommerce_variable_product_before_variations', [$this, 'showUnchargeableMarkupNotice']);

		// Handle AJAX requests to reapply markups to variations
		add_action('wp_ajax_handleMarkupReapplication', [$this, 'handleMarkupReapplication']);

		// Handle AJAX requests to get formatted base price for confirmation messages
		add_action('wp_ajax_getFormattedBasePrice', [$this, 'getFormattedBasePrice']);

		// Handle AJAX requests to refresh general panel after price changes
		add_action('wp_ajax_mt2mba_refresh_general_panel', [$this, 'refreshProductGeneralPanel']);

		// Handle AJAX requests to re-evaluate the unchargeable-markup notice
		add_action('wp_ajax_mt2mba_unchargeable_notice', [$this, 'refreshUnchargeableNotice']);
	}

	/**
	 * Enqueue markup reapplication JavaScript.
	 * Sets up necessary scripts and localization data.
	 *
	 * @param	string	$hook	The current admin page hook
	 */
	public function enqueueMarkupScripts($hook): void {
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
				[
					'ajaxUrl' => admin_url('admin-ajax.php'),
					'security' => wp_create_nonce('handleMarkupReapplication'),
					'variationsNonce' => wp_create_nonce('load-variations'),
					'i18n' => [
						'reapplyMarkupss' => __('Reapply markups to prices', 'markup-by-attribute-for-woocommerce'),
						'confirmReapply' => __('Reprice variations at %s, plus or minus the markups?', 'markup-by-attribute-for-woocommerce'),
						'failedRecalculating' => __('Failed to reapply markups. Please try again.', 'markup-by-attribute-for-woocommerce'),
						// Distinct from the above on purpose: this one is shown only after the
						// reprice has already committed, so it must not tell the shop owner
						// their prices are unchanged when they are not.
						'failedRefreshing' => __('Markups were reapplied, but the variations list could not be refreshed. Reload the page to see the new prices.', 'markup-by-attribute-for-woocommerce')
					]
				]
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
	public function handleMarkupReapplication(): void {
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

			} catch (Throwable $e) {
				// Rollback transaction on any error to maintain data integrity
				$wpdb->query('ROLLBACK');
				throw $e;
			}

		} catch (Throwable $e) {
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
	public function getFormattedBasePrice(): void {
		check_ajax_referer('handleMarkupReapplication', 'security');

		if (!current_user_can('edit_products')) {
			wp_send_json_error(['message' => __('Permission denied', 'markup-by-attribute-for-woocommerce')]);
			return;
		}

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
	public function refreshProductGeneralPanel(): void {
		check_ajax_referer('handleMarkupReapplication', 'security');

		if (!current_user_can('edit_products')) {
			wp_send_json_error(['message' => __('Permission denied', 'markup-by-attribute-for-woocommerce')]);
			return;
		}

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;

		if (!$product_id) {
			wp_send_json_error();
			return;
		}

		// Return base price values for JS to update fields directly
		wp_send_json_success([
			'base_regular_price' => get_post_meta($product_id, 'mt2mba_base_regular_price', true),
			'base_sale_price'    => get_post_meta($product_id, 'mt2mba_base_sale_price',    true),
		]);
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
	public function handleBulkPriceAction($bulk_action, $data, $product_id, $variations): void {
		// Determine which class should extend PriceMarkupHandler based on the bulk_action
		if ($bulk_action == 'variable_regular_price' || $bulk_action == 'variable_sale_price') {
			// Set either the regular price or the sale price
			$handler = new Handlers\PriceSetHandler($bulk_action, $data, $product_id, $variations);

		} elseif (strpos($bulk_action, '_price_increase') !== false || strpos($bulk_action, '_price_decrease') !== false) {
			// Increase or decrease the regular price or the sale price
			$handler = new Handlers\PriceUpdateHandler($bulk_action, $data, $product_id, $variations);

		} elseif ($bulk_action == 'delete_all') {
			// Delete all markup metadata for product
			$handler = new Handlers\MarkupDeleteHandler($product_id);

		} else {
			// If none of the above, leave and don't execute $handler
			return;
		}

		// Each handler received everything it needs above; the run takes no arguments
		$handler->processProductMarkups();

		// The handlers write meta through $wpdb, bypassing the object cache, and
		// WooCommerce's post-hook sync never invalidates the variations. Flush them
		// here so a persistent cache (Redis, Memcached) does not keep serving stale prices.
		clean_post_cache($product_id);
		foreach ($variations as $variation_id) {
			clean_post_cache($variation_id);
		}
	}
	//endregion

	//region UI DISPLAY
	/**
	 * Display base price fields in product general options panel.
	 * Shows readonly fields for base regular and sale prices.
	 */
	public function addBasePriceFields(): void {
		global $post;

		if ($post) {
			$base_regular_price = get_post_meta($post->ID, 'mt2mba_base_regular_price', true);
			$base_sale_price = get_post_meta($post->ID, 'mt2mba_base_sale_price', true);
			$currency_symbol = ' (' . get_woocommerce_currency_symbol() . ')';

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

			// Sale Price Field (always shown, even if empty)
			woocommerce_wp_text_input([
				'id'				=> 'base_sale_price',
				'label'				=> __('Sale base price', 'markup-by-attribute-for-woocommerce') . $currency_symbol,
				'description'		=> __('Sale base price for the variations before markup', 'markup-by-attribute-for-woocommerce'),
				'value'				=> $base_sale_price,
				'type'				=> 'text',
				'desc_tip'			=> true,
				'class'				=> 'wc_input_price',
				'data_type'			=> 'price',
				'custom_attributes'	=> ['readonly' => 'readonly']
			]);

			echo '<div id="base_price_info"><p class="form-field">' .
				'<span class="base-price-info dashicons dashicons-info"></span>' .
				__('Change base prices with the <em>Bulk actions</em> on the <b>Variations</b> tab.', 'markup-by-attribute-for-woocommerce') .
				'</p></div>';
			echo '</div>';
		}
	}

	/**
	 * Warn about markups that are advertised but can never be charged
	 *
	 * An attribute left as "Any" on a variation makes WooCommerce offer every one
	 * of that attribute's options in the drop-down (see read_variation_attributes()
	 * in WooCommerce's variable-product data store), markup annotation included --
	 * but the cart resolves to the "Any" variation, whose price carries no markup
	 * for the option the customer picked. The markup is advertised and never
	 * charged, and when it is negative the customer is overcharged against what
	 * the drop-down promised.
	 *
	 * Informational only: the configuration is legitimate for a store that means
	 * it, so nothing is blocked or altered.
	 *
	 * @since 4.8.0
	 */
	public function showUnchargeableMarkupNotice(): void {
		global $post;
		if (!$post) return;

		// The wrapper is emitted even when there is nothing to say: it is the target
		// refreshUnchargeableNotice() replaces after [Save changes], which alters the
		// answer without re-rendering this panel.
		echo '<div id="mt2mba-unchargeable-notice">' .
			$this->buildUnchargeableNotice((int) $post->ID) .
			'</div>';
	}

	/**
	 * Rebuild the notice after variations are saved
	 *
	 * [Save changes] reloads only the variation rows, not the panel this notice
	 * sits in, so without this the notice would stay stale until [Update].
	 *
	 * @since 4.8.0
	 */
	public function refreshUnchargeableNotice(): void {
		check_ajax_referer('handleMarkupReapplication', 'security');

		if (!current_user_can('edit_products')) {
			wp_send_json_error(['message' => __('Permission denied', 'markup-by-attribute-for-woocommerce')]);
			return;
		}

		$product_id = isset($_POST['product_id']) ? absint($_POST['product_id']) : 0;
		if (!$product_id) {
			wp_send_json_error();
			return;
		}

		wp_send_json_success(['html' => $this->buildUnchargeableNotice($product_id)]);
	}

	/**
	 * Build the notice markup, or an empty string when there is nothing to report
	 *
	 * @param  int    $product_id The product to examine
	 * @return string             Ready-to-echo HTML, already escaped
	 */
	private function buildUnchargeableNotice($product_id): string {
		// A store that hides markups in the drop-down advertises nothing, so the
		// notice would be false. Known gap: "Add Markup to Name?" bakes the markup
		// into the term name regardless of this setting, and is not detected.
		if (MT2MBA_DROPDOWN_BEHAVIOR === 'hide') return '';

		$product = wc_get_product($product_id);
		if (!$product || !$product->is_type('variable')) return '';

		$labels = self::findUnchargeableAttributes($product, $product_id, $product->get_children());
		if (empty($labels)) return '';

		// Not class="notice notice-warning": core restyled .notice between WP 5.7
		// and 7.1, and WooCommerce's panel rules outrank a plain class selector.
		// Styled in admin-style.css instead, so it looks the same on every version.
		$html = '<div class="mt2mba-unchargeable"><p><strong>' .
			esc_html(MT2MBA_PLUGIN_NAME) . '</strong> &mdash; ' .
			esc_html__('Global attributes set to "Any" will not reflect the markup in the price:', 'markup-by-attribute-for-woocommerce') .
			'</p><ul>';

		// Labelled the way WooCommerce labels the variation rows ("Any Colour…") so
		// the owner can match the warning to the screen. The ellipsis entity sits
		// outside the escaped text so it cannot become &amp;hellip;.
		foreach ($labels as $label) {
			$html .= '<li>' . esc_html(sprintf(
				/* translators: %s: attribute name, e.g. Colour */
				__('Any %s', 'markup-by-attribute-for-woocommerce'),
				$label
			)) . '&hellip;</li>';
		}

		return $html . '</ul></div>';
	}

	/**
	 * Find every attribute whose advertised markups no variation can charge
	 *
	 * Static and free of page context so the test suite can call it directly.
	 * Collecting every attribute costs nothing extra: the variation rows are one
	 * bulk read however many attributes are flagged.
	 *
	 * @since  4.8.0
	 * @param  object $product       The variable product
	 * @param  int    $product_id    The product's ID
	 * @param  array  $variation_ids Every variation belonging to the product
	 * @return array                 Display labels of the offending attributes
	 */
	public static function findUnchargeableAttributes($product, $product_id, array $variation_ids): array {
		$candidates = [];

		foreach ($product->get_attributes() as $attribute) {
			// Local attributes cannot carry markups, and an attribute with "Used for
			// variations" off renders no drop-down, so it advertises nothing. Unlike
			// getAttributeData(), which keeps such attributes: their markups still
			// belong in the markup table.
			if (!$attribute->is_taxonomy() || !$attribute->get_variation()) continue;

			$selected = $attribute->get_options();
			if (empty($selected)) continue;

			// Read the same meta the storefront reads (Frontend\Options), so the notice
			// fires exactly when a customer is shown a markup. A product that has never
			// been repriced has no such meta and is not flagged.
			$advertised = [];
			foreach ($selected as $term_id) {
				$amount = get_metadata('post', $product_id, "mt2mba_{$term_id}_markup_amount", true);
				if ($amount !== '' && (float) $amount != 0) {
					$advertised[(int) $term_id] = $amount;
				}
			}

			// Only whether it advertises anything matters; the amounts do not appear
			// in the notice, so they are not carried forward.
			if (!empty($advertised)) {
				$candidates[] = $attribute->get_name();
			}
		}

		// Nothing advertised means no query is needed at all -- the common case on
		// a store where most products carry no markups
		if (empty($candidates) || empty($variation_ids)) return [];

		// One bulk read covers every variation's attribute assignments
		$assigned = [];
		foreach (Handlers\BulkMetaIO::fetchMetaLike($variation_ids, 'attribute_pa_%') as $row) {
			$assigned[(int) $row->post_id][substr($row->meta_key, 10)] = $row->meta_value;
		}

		$flagged = [];
		foreach ($candidates as $taxonomy) {
			foreach ($variation_ids as $variation_id) {
				// empty(), not === '': WooCommerce writes '' for an explicit "Any", but
				// an attribute that gained "Used for variations" after the variations
				// existed may have no row at all. Missing and empty both mean Any.
				if (empty($assigned[(int) $variation_id][$taxonomy])) {
					$flagged[] = wc_attribute_label($taxonomy);
					break;		// one mention per attribute, however many variations
				}
			}
		}

		return $flagged;
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
	private function processVariationsWithMarkup($product_id, $variations): void {
		// Both passes run inside handleMarkupReapplication()'s transaction, so the
		// handlers must not start their own (owns_transaction = false)
		$base_regular_price = get_post_meta($product_id, 'mt2mba_base_regular_price', true);
		$data = ['value' => $base_regular_price];
		$handler = new Handlers\PriceSetHandler('variable_regular_price', $data, $product_id, $variations, false);
		$handler->processProductMarkups();

		$base_sale_price = get_post_meta($product_id, 'mt2mba_base_sale_price', true);
		if (!empty($base_sale_price)) {
			$data = ['value' => $base_sale_price];
			$handler = new Handlers\PriceSetHandler('variable_sale_price', $data, $product_id, $variations, false);
			$handler->processProductMarkups();
		}
	}

	/**
	 * Clean up caches and transients after updates.
	 * Ensures proper cache invalidation for updated data.
	 *
	 * @param	int		$product_id	The ID of the product
	 * @param	array	$variations	List of variation IDs
	 */
	private function cleanupCachesAndTransients($product_id, $variations): void {
		// Clear post cache
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