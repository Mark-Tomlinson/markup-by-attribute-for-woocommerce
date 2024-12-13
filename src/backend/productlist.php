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
	 * Singleton because we only want one instance of the product list at a time.
	 */
	private static $instance = null;

	/**
     * Cache for variable product IDs to avoid repeated checks
     * @var array
     */
    private $variable_products = [];

	/**
	 * Product base price used throughout
	 * @var array
	 */
	private $base_price = '';

	// Public method to get the instance
	public static function get_instance() {
		if (self::$instance === null) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// Prevent cloning of the instance
	public function __clone() {}

	// Prevent unserializing of the instance
	public function __wakeup() {}

	// Private constructor
    private function __construct() {
        // Add filter to modify product columns
        add_filter('manage_edit-product_columns', array($this, 'modify_product_columns'), 20);

        // Add action to populate columns
        add_action('manage_product_posts_custom_column', array($this, 'populate_columns'), 10, 2);

		// Add action to filter products by attribute
		add_action('pre_get_posts', array($this, 'filter_products_by_attribute'));

		// Add action to enqueue our JavaScript
		add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

		// Add bulk action
		add_filter('bulk_actions-edit-product', array($this, 'register_bulk_action'));
		add_filter('handle_bulk_actions-edit-product', array($this, 'handle_bulk_action'), 10, 3);

		// Add action to include custom CSS
		add_action('admin_enqueue_scripts', array($this, 'enqueue_styles'));
	}

	/**
	 * Enqueue scripts needed for product list markup handling
	 */
	public function enqueue_scripts($hook) {
		if ($hook !== 'edit.php') {
			return;
		}
		if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'product') {
			return;
		}
	
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
				'security' => wp_create_nonce('mt2mba_reapply_markup'),
				'i18n' => array(
					'processing' => __('Please wait; processing product %1$s of %2$s...', 'markup-by-attribute'),
					'processed' => _n(
						'%s product processed successfully.',
						'%s products processed successfully.',
						1,
						'markup-by-attribute'
					),
					'processedPlural' => _n(
						'%s product processed successfully.',
						'%s products processed successfully.',
						2,
						'markup-by-attribute'
					)
				)
			)
		);
	}
	
	/**
	 * Enqueue styles for product list handling
	 */
	public function enqueue_styles($hook) {
		if ($hook !== 'edit.php') {
			return;
		}
		
		if (!isset($_GET['post_type']) || $_GET['post_type'] !== 'product') {
			return;
		}

		wp_enqueue_style(
			'mt2mba-admin-styles',
			plugins_url('css/admin-style.css', dirname(__FILE__)),
			array(),
			MT2MBA_VERSION
		);
	}

	/**
	 * Register the bulk action
	 */
	public function register_bulk_action($bulk_actions) {
		$new_actions = array();
		
		// Rebuild the array in our desired order
		foreach ($bulk_actions as $key => $action) {
			$new_actions[$key] = $action;
			
			// Add our action after 'Edit'
			if ($key === 'edit') {
				$new_actions['reapply_markups'] = __('Reapply Markups', 'markup-by-attribute');
			}
		}
		return $new_actions;
	}

	/**
	 * Handle the bulk action
	 */
	public function handle_bulk_action($redirect_to, $doaction, $post_ids) {
		if ($doaction !== 'reapply_markups') {
			return $redirect_to;
		}

		// Filter to only get variable products
		$variable_products = array_filter($post_ids, function($product_id) {
			$product = wc_get_product($product_id);
			return $product && $product->is_type('variable');
		});

		if (!empty($variable_products)) {
			// Add products to process to the redirect URL
			$redirect_to = add_query_arg('reapply_markups_ids', implode(',', $variable_products), $redirect_to);
		}
		return $redirect_to;
	}

	/**
	 * Modify the columns in the product list table
	 *
	 * @param   array   $columns    Existing columns.
	 * @return  array               Modified columns.
	 */
	public function modify_product_columns($columns) {
		$new_columns = array();
		foreach ($columns as $key => $column) {
			$new_columns[$key] = $column;
			if ($key === 'price') {
				$new_columns['mt2mba_base_price'] = __('Base Price', 'markup-by-attribute');
			}
			if ($key === 'product_tag') {
				$new_columns['product_attributes'] = __('Attributes', 'markup-by-attribute');
			}
		}
		return $new_columns;
	}

	/**
	 * Static cache for term markups to avoid repeated database queries
	 */
	private static $markup_cache = [];

	/**
	 * Check if an attribute has any markup terms
	 * 
	 * @param string $taxonomy The attribute taxonomy
	 * @return bool True if any terms have markup
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
     * Populate the custom columns
     *
     * @param	string	$column  Name of the column to display.
     * @param	int		$post_id ID of the current product.
     */
    public function populate_columns($column, $post_id) {
		// Get product
        $product = wc_get_product($post_id);
		// Get appropriate base price
		$this->base_price = '';
		if ($product->is_on_sale()) {
			$this->base_price = get_post_meta($post_id, 'mt2mba_base_sale_price', true);
		} else {
			$this->base_price = get_post_meta($post_id, 'mt2mba_base_regular_price', true);
		}

        // Cache whether this is a variable product on first check
        if (!isset($this->variable_products[$post_id])) {
            $this->variable_products[$post_id] = $product && $product->is_type('variable');
        }

		switch ($column) {
			case 'product_attributes':
				$this->render_attributes_column($product, $post_id);
				break;

			case 'mt2mba_base_price':
				if (!$this->variable_products[$post_id]) {
					echo '<span class="na">–</span>';
					break;
				}
				if ($this->base_price === '') {
					echo '<span class="na">–</span>';
					break;
				}
				echo wc_price($this->base_price);
				break;
		}
	}

    /**
     * Render the attributes column content
     *
     * @param WC_Product $product Product object
     * @param int        $post_id Product ID
     */
    private function render_attributes_column($product, $post_id) {
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
        if ($this->variable_products[$post_id] && $has_markup) {
            echo '<br/><a href="#" class="js-mt2mba-reapply-markup" ' .
                'data-product-id="' . esc_attr($post_id) . '" ' .
                'title="' . esc_attr__('Reapply Markups', 'markup-by-attribute') . '">' .
                '<span class="dashicons dashicons-update"></span>' .
                __('Reprice', 'markup-by-attribute') . '</a>';
			// Add hover text to reprice icon
			echo "<script>
				jQuery(document).ready(function($) {
					$('.js-mt2mba-reapply-markup[data-product-id=\"{$post_id}\"]')
						.attr('title', '" . 
						esc_js(sprintf(
							__('Reapply markups using base price: %s', 'markup-by-attribute'),
							html_entity_decode(strip_tags(wc_price($this->base_price)))
						)) . 
						"');
				});
				</script>";
        }
    }

	/**
	 * Filter products by attribute in the admin product list.
	 * @param	WP_Query	$query	The WordPress query object.
	 */
	public function filter_products_by_attribute($query) {
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
}