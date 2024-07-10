<?php
/**
 * Product Attributes Display Handler for Markup by Attribute
 *
 * @package markup-by-attribute-for-woocommerce
 */

if (!defined('ABSPATH')) exit; // Exit if accessed directly

class MT2MBA_Product_Attributes_Display {

    public function __construct() {
        add_filter('manage_edit-product_columns', array($this, 'add_attributes_column'));
        add_action('manage_product_posts_custom_column', array($this, 'populate_attributes_column'), 10, 2);
    }

    public function add_attributes_column($columns) {
        $columns['product_attributes'] = __('Product Attributes', 'markup-by-attribute');
        return $columns;
    }

    public function populate_attributes_column($column, $post_id) {
        if ('product_attributes' === $column) {
            $product = wc_get_product($post_id);
            $attributes = $product->get_attributes();

            if (!empty($attributes)) {
                $output = '<ul style="margin: 0; padding-left: 1em;">';
                foreach ($attributes as $attribute) {
                    if ($attribute->is_taxonomy()) {
                        $terms = wp_get_post_terms($post_id, $attribute->get_name(), array("fields" => "names"));
                        if (!is_wp_error($terms)) {
                            $attribute_name = wc_attribute_label($attribute->get_name());
                            $attribute_value = implode(', ', $terms);
                            $output .= "<li><strong>{$attribute_name}:</strong> {$attribute_value}</li>";
                        }
                    } else {
                        $attribute_name = $attribute->get_name();
                        $attribute_value = $attribute->get_options();
                        $attribute_value = implode(', ', $attribute_value);
                        $output .= "<li><strong>{$attribute_name}:</strong> {$attribute_value}</li>";
                    }
                }
                $output .= '</ul>';
                echo $output;
            } else {
                echo __('No attributes', 'markup-by-attribute');
            }
        }
    }
}