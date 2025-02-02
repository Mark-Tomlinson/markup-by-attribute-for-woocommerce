<?php
namespace mt2Tech\MarkupByAttribute\Backend;

/**
 * Product Attributes Display Handler for Markup by Attribute
 * This class handles the display and filtering of product attributes in the WooCommerce product list.
 *
 * @package	markup-by-attribute-for-woocommerce
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class ProductList {
	/**
	 * Singleton instance of ProductList
	 * 
	 * @var ProductList|null
	 */
	private static $instance = null;

	/**
	 * Cache of variable product IDs to avoid repeated lookups
	 * 
	 * @var	array
	 */
	private $variable_products = [];

	/**
	 * Cache of markup values by taxonomy
	 * 
	 * @var	array
	 */
	private static $markup_cache = [];

	/**
	 * Get singleton instance of ProductList
	 * 
	 * @return	ProductList	Single instance of this class
	 */
	public static function get_instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Prevent object cloning
	 */
	public function __clone() {}

	/**
	 * Prevent object unserialization
	 */
	public function __wakeup() {}

	/**
	 * Initialize ProductList and register WordPress hooks
	 */
	private function __construct() {
		// Column Management
		add_filter('manage_edit-product_columns', [$this, 'addCustomColumns'], 20);
		add_action('manage_product_posts_custom_column', [$this, 'renderColumnContent'], 10, 2);
		
		// Attribute Filtering
		add_action('pre_get_posts', [$this, 'filterProductsByAttribute']);

		// Asset Management  
		add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);

		// Bulk Actions
		add_filter('bulk_actions-edit-product', [$this, 'addBulkActions']);
		add_filter('handle_bulk_actions-edit-product', [$this, 'processBulkActions'], 10, 3);

		// Add AJAX handler for row refresh
		add_action('wp_ajax_mt2mba_refresh_product_row', array($this, 'refreshProductRow'));
	}

	/**
	 * Enqueue required assets for product list functionality
	 * 
	 * @param	string	$hook	Current admin page hook
	 */
	public function enqueueAssets($hook) {
		// Product List page?
		if (!$this->is_product_list_page($hook)) return;

		// Enqueue Assets
		$this->enqueue_scripts($hook);
		$this->enqueue_styles($hook);
	}

	/**
	 * Check if current page is the WooCommerce product list
	 *
	 * @param	string	$hook	Current admin page hook
	 * @return	bool			True if on product list page
	 */
	private function is_product_list_page($hook) {
		return $hook === 'edit.php' && 
				isset($_GET['post_type']) && 
				$_GET['post_type'] === 'product';
	}

	/**
	 * Enqueue JavaScript files for product list
	 * 
	 * @param	string	$hook	Current admin page hook
	 */
	public function enqueue_scripts($hook) {
		wp_enqueue_script(
			'mt2mba-product-list-markup',
			plugins_url('js/jq-mt2mba-reapply-markups-productlist.js', dirname(__FILE__)),
			array('jquery'),
			MT2MBA_VERSION,
			true
		);
	
		wp_localize_script(
			'mt2mba-product-list-markup', 
			'mt2mbaListLocal',
			array(
				'security' => wp_create_nonce('handleMarkupReapplication'),
				'i18n' => array(
					'processing' => __('Please wait; processing product %1$s of %2$s...', 'markup-by-attribute-for-woocommerce'),
					'processed' => _n(
						'%s product processed successfully.',
						'%s products processed successfully.',
						1,
						'markup-by-attribute-for-woocommerce'
					),
					'processedPlural' => _n(
						'%s product processed successfully.',
						'%s products processed successfully.',
						2,
						'markup-by-attribute-for-woocommerce'
					)
				)
			)
		);
	}
	
	/**
	 * Enqueue CSS files for product list
	 * 
	 * @param	string	$hook	Current admin page hook
	 */
	public function enqueue_styles($hook) {
		wp_enqueue_style(
			'mt2mba-admin-styles',
			plugins_url('css/admin-style.css', dirname(__FILE__)),
			array(),
			MT2MBA_VERSION
		);
	}

	/**
	 * Add custom columns to product list table
	 * 
	 * @param	array	$columns	Existing columns
	 * @return	array				Modified columns
	 */
	public function addCustomColumns($columns) {
		$new_columns = array();
		foreach ($columns as $key => $column) {
			$new_columns[$key] = $column;
			if ($key === 'price') {
				// This column will get both sets of classes 
				$new_columns['mt2mba_base_price'] = __('Base Price', 'markup-by-attribute-for-woocommerce');
			}
			if ($key === 'product_tag') {
				$new_columns['product_attributes'] = __('Attributes', 'markup-by-attribute-for-woocommerce');
			}
		}
		return $new_columns;
	}

	/**
	 * Render content for custom columns
	 * 
	 * @param	string	$column		Column identifier
	 * @param	int		$product_id	Product ID
	 */
	public function renderColumnContent($column, $product_id) {	// renamed from populate_columns
		// Get product
		$product = wc_get_product($product_id);
		if (!$product) return;	// No product! 

		// Get appropriate base price
		$base_price = '';
		if ($product->is_on_sale()) {
			$base_price = get_post_meta($product_id, 'mt2mba_base_sale_price', true);
		} else {
			$base_price = get_post_meta($product_id, 'mt2mba_base_regular_price', true);
		}

		// Cache whether this is a variable product on first check
		if (!isset($this->variable_products[$product_id])) {
			$this->variable_products[$product_id] = $product && $product->is_type('variable');
		}

		switch ($column) {
			case 'product_attributes':
				$this->renderAttributesColumn($product, $product_id, $base_price);
				break;

			case 'mt2mba_base_price':
				$this->renderBasePriceColumn($product, $product_id);
				break;
		}
	}

	/**
	 * Render base price column content with regular and sale prices
	 * 
	 * @param	WC_Product	$product	Product object
	 * @param	int			$product_id	Product ID
	 */
	private function renderBasePriceColumn($product, $product_id) {
		// Ignore if not a variable product
		if (!$this->variable_products[$product_id]) {
			echo '<span class="na">–</span>';
			return;
		}

		// Get base prices
		$base_regular_price = get_post_meta($product_id, 'mt2mba_base_regular_price', true);
		$base_sale_price = get_post_meta($product_id, 'mt2mba_base_sale_price', true);

		// If on sale, cross out regular price and display sale price
		if ($base_sale_price !== '') {
			printf(
				'<del>%s</del><br>%s',
				wc_price($base_regular_price),
				wc_price($base_sale_price)
			);

		// Else (if not on sale) print regular price
		} else {
			if ($base_regular_price !== '') {
				echo wc_price($base_regular_price);
			} else {
				echo '<span class="na">–</span>';
			}
		}
		return;
	}

	/**
	 * Render attributes column content with markup information
	 * 
	 * @param	WC_Product	$product	Product object
	 * @param	int			$product_id	Product ID
	 */
	private function renderAttributesColumn($product, $product_id, $base_price) {
		$attributes = $product->get_attributes();
		
		if (empty($attributes)) {
			echo '<span class="na">–</span>';
			return;
		}

		$output = array();
		$has_markup = false;

		foreach ($attributes as $attribute) {
			if ($attribute->is_taxonomy()) {
				$attribute_name = wc_attribute_label($attribute->get_name());
				$taxonomy = $attribute->get_name();
				
				if ($this->attribute_has_markup($taxonomy)) {
					$has_markup = true;
				}
			} else {
				$attribute_name = $attribute->get_name();
				$taxonomy = sanitize_title($attribute_name);
			}

			$filter_url = add_query_arg(array(
				'filter_product_attribute' => $taxonomy,
				'post_type' => 'product'
			), admin_url('edit.php'));

			$output[] = '<a href="' . esc_url($filter_url) . '">' . esc_html($attribute_name) . '</a>';
		}
		
		echo implode(', ', $output);

		// Only show reapply link for variable products with markup-enabled attributes
		if ($this->variable_products[$product_id] && $has_markup) {
			echo '<br/><a href="#" class="js-mt2mba-reapply-markup" ' .
				'data-product-id="' . esc_attr($product_id) . '" ' .
				'title="' . esc_attr__('Reapply Markups', 'markup-by-attribute-for-woocommerce') . '">' .
				'<span class="dashicons dashicons-update"></span>' .
				__('Reprice', 'markup-by-attribute-for-woocommerce') . '</a>';
			// Add hover text to reprice icon
			echo "<script>
				jQuery(document).ready(function($) {
					$('.js-mt2mba-reapply-markup[data-product-id=\"{$product_id}\"]')
						.attr('title', '" . 
						esc_js(sprintf(
							__('Reapply markups using base price: %s', 'markup-by-attribute-for-woocommerce'),
							html_entity_decode(strip_tags(wc_price($base_price)))
						)) . 
						"');
				});
				</script>";
		}
	}

	/**
	 * Check if an attribute taxonomy has any terms with markup
	 * 
	 * @param	string	$taxonomy	Attribute taxonomy name
	 * @return	bool				True if markup exists
	 */
	private function attribute_has_markup($taxonomy) {
		// Check cache first
		if (isset(self::$markup_cache[$taxonomy])) {
			return self::$markup_cache[$taxonomy];
		}

		$terms = get_terms([
			'taxonomy' => $taxonomy,
			'hide_empty' => false,
		]);

		foreach ($terms as $term) {
			$markup = get_term_meta($term->term_id, 'mt2mba_markup', true);
			if (!empty($markup)) {		// Set flag and return true when the first markup is found
				self::$markup_cache[$taxonomy] = true;
				return true;
			}
		}

		self::$markup_cache[$taxonomy] = false;
		return false;
	}

	/**
	 * Filter products in admin list by attribute
	 * 
	 * @param	WP_Query	$query	WordPress query object
	 */
	public function filterProductsByAttribute($query) {
		global $typenow, $wp_query;

		if ($typenow == 'product' && is_admin()) {
			$filter_attribute = isset($_GET['filter_product_attribute']) ? sanitize_text_field($_GET['filter_product_attribute']) : '';

			if (!empty($filter_attribute)) {
				$taxonomy = wc_attribute_taxonomy_name($filter_attribute);

				if (taxonomy_exists($taxonomy)) {
					// For taxonomy attributes
					$query->set('tax_query', array(array(
						'taxonomy' => $taxonomy,
						'field' => 'slug',
						'terms' => get_terms($taxonomy, array('fields' => 'slugs')),
						'operator' => 'IN'
					)));
				} else {
					// For custom product attributes
					$meta_query = $query->get('meta_query', array());
					$meta_query[] = array(
						'key' => '_product_attributes',
						'value' => '"' . $filter_attribute . '"',
						'compare' => 'LIKE'
					);
					$query->set('meta_query', $meta_query);
				}
			}
		}
	}

	/**
	 * Add bulk actions for markup handling
	 * 
	 * @param	array	$bulk_actions	Existing bulk actions
	 * @return	array					Modified bulk actions
	 */
	public function addBulkActions($bulk_actions) {
		$new_actions = array();
		
		// Rebuild the array in our desired order
		foreach ($bulk_actions as $key => $action) {
			$new_actions[$key] = $action;
			
			// Add our action after 'Edit'
			if ($key === 'edit') {
				$new_actions['reapply_markups'] = __('Reapply Markups', 'markup-by-attribute-for-woocommerce');
			}
		}
		return $new_actions;
	}

	/**
	 * Process bulk markup actions
	 * 
	 * @param	string	$redirect_to	Redirect URL
	 * @param	string	$doaction		Action being performed
	 * @param	array	$product_ids	Selected product IDs
	 * @return	string					Modified redirect URL
	 */
	public function processBulkActions($redirect_to, $doaction, $product_ids) {
		if ($doaction !== 'reapply_markups') {
			return $redirect_to;
		}

		// Filter to only get variable products
		$variable_products = array_filter($product_ids, function($product_id) {
			$product = wc_get_product($product_id);
			return $product && $product->is_type('variable');
		});

		if (!empty($variable_products)) {
			// Add products to process to the redirect URL
			$redirect_to = add_query_arg('reapply_markups_ids', implode(',', $variable_products), $redirect_to);
		}
		return $redirect_to;
	}

	public function refreshProductRow() {
		check_ajax_referer('handleMarkupReapplication', 'security');
		
		$product_id = absint($_REQUEST['product_id']);
		$product = wc_get_product($product_id);

		if (!$product) {
			wp_send_json_error(['message' => __('Product not found', 'markup-by-attribute-for-woocommerce')]);
			return;
		}

		// Get the price HTML using WooCommerce's own method
		$price_html = $product->get_price_html();
		wp_send_json_success(['price' => $price_html]);
	}
}