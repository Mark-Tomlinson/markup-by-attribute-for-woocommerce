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
		// Add action to populate attribute column
		add_action('manage_product_posts_custom_column', array($this, 'populate_attributes_column'), 10, 2);
		// Add action to include custom CSS
		add_action('admin_head', array($this, 'add_custom_styling'));
		// Add action to filter products by attribute
		add_action('pre_get_posts', array($this, 'filter_products_by_attribute'));
	}

	/**
	 * Modify the columns in the product list table if 'product_attributes' column doesn't exist.
	 *
	 * @param	array	$columns	Existing columns.
	 * @return	array				Modified columns.
	 */
	public function modify_product_columns($columns) {
		// Check if 'product_attributes' column already exists
		if (isset($columns['product_attributes'])) {
			return $columns; // Return unmodified if the column already exists
		}
		// Insert attributes column after the product tag column
		$new_columns = array();
		foreach ($columns as $key => $column) {
			$new_columns[$key] = $column;
			if ($key === 'product_tag') {
				$new_columns['product_attributes'] = __('Attributes', 'markup-by-attribute');
			}
		}
		// If we couldn't insert after 'product_tag', add it to the end
		if (!isset($columns['product_attributes'])) {
			$new_columns['product_attributes'] = __('Attributes', 'markup-by-attribute');
		}
		return $new_columns;
	}
	/**
	 * Populate the custom attributes column.
	 *
	 * @param	string	$column		Name of the column to display.
	 * @param	int		$post_id	ID of the current product.
	 */
	public function populate_attributes_column($column, $post_id) {
		if ('product_attributes' === $column) {
			$product = wc_get_product($post_id);
			$attributes = $product->get_attributes();

			if (!empty($attributes)) {
				$output = array();
				foreach ($attributes as $attribute) {
					// Get the name of the attribute
					if ($attribute->is_taxonomy()) {
						$attribute_name = wc_attribute_label($attribute->get_name());
						$taxonomy = $attribute->get_name();
					} else {
						$attribute_name = $attribute->get_name();
						$taxonomy = sanitize_title($attribute_name);
					}
					// Create a filter URL for each attribute
					$filter_url = add_query_arg(array(
						'filter_product_attribute' => $taxonomy,
						'post_type' => 'product'
					), admin_url('edit.php'));
					// Create a clickable link for each attribute
					$output[] = '<a href="' . esc_url($filter_url) . '">' . esc_html($attribute_name) . '</a>';
				}
				echo implode(', ', $output);
			} else {
				echo '<span class="na">–</span>';
			}
		}
	}

	/**
	 * Add custom CSS for styling the product list table.
	 */
	public function add_custom_styling() {
		echo '<style>
			.wp-list-table .column-product_attributes {
				width: 11%;
			}
			.wp-list-table {
				table-layout: fixed;
			}
			.wp-list-table td {
				overflow: hidden;
				text-overflow: ellipsis;
			}
		</style>';
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