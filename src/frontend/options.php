<?php
namespace mt2Tech\MarkupByAttribute\Frontend;
/**
 * Set the dropdown box with available options and the associated markup.
 * @author	Mark Tomlinson
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit();

class Options {
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
		add_filter('woocommerce_dropdown_variation_attribute_options_html', array($this, 'mt2mba_dropdown_options_markup_html'), 10, 2);
	}

	/**
	 * The hooked function that will add the variation description to the dropdown options elements
	 *
	 * @param	string	$html	The HTML of the options drop-down box
	 * @param	array	$args	Array of available options
	 * @return	string			The modified HTML of the options drop-down box
	 */
	public function mt2mba_dropdown_options_markup_html($html, $args) {
		// Set globals
		global $mt2mba_utility;
	
		// Extract attribute from $args
		$attribute = $args['attribute'];
	
		$strip_markups = false;
		// Only check for zero price handling if markup is in the name
		if (get_option(REWRITE_TERM_NAME_PREFIX . wc_attribute_taxonomy_id_by_name($attribute)) == 'yes') {
			if(MT2MBA_ALLOW_ZERO === 'yes' && $args['product'] && $args['product']->get_price() == 0) {
				$strip_markups = true;
			} else {
				return $html;
			}
		}

		// Extract remaining content from $args
		$product				= $args['product'];
		$name					= $args['name'] ? $args['name'] : 'attribute_' . sanitize_title($attribute);
		$id						= $args['id'] ? $args['id'] : sanitize_title($attribute);
		$class					= $args['class'];
		$show_option_none		= $args['show_option_none'] ? TRUE : FALSE;
		$show_option_none_text	= $args['show_option_none'] ? $args['show_option_none'] : __('Choose an option', 'woocommerce');
		$options				= $args['options'];
	
		// If $options is empty, get them from the product attributes
		if (empty($options) && !empty($product) && !empty($attribute)) {
			$attributes = $product->get_variation_attributes();
			$options = $attributes[$attribute];
		}
	
		// Start building output HTML
		$html = PHP_EOL .
			'<select ' .
			'id="' . esc_attr($id) . '" ' .
			'class="' . esc_attr($class) . '" ' .
			'name="' . esc_attr($name) . '" ' .
			'data-attribute_name="attribute_' . esc_attr(sanitize_title($attribute)) . '" ' .
			'data-show_option_none="' . ($show_option_none ? 'yes' : 'no') . '">' .
			PHP_EOL .
			'<option value="">' . esc_html($show_option_none_text) . '</option>';
	
		// Build <OPTION>s within <SELECT>
		if (!empty($options)) {
			if ($product && taxonomy_exists($attribute)) {  // product exists and attribute is global
				$terms = wc_get_product_terms($product->get_id(), $attribute, array('fields' => 'all'));
				foreach ($terms as $term) {
					if (in_array($term->slug, $options)) {
						if ($strip_markups) {
							$term_name = $mt2mba_utility->stripMarkupAnnotation($term->name);
							$markup = '';
						} else {
							$term_name = $term->name;
							$markup = get_metadata('post', $product->get_id(), 'mt2mba_' . $term->term_id . '_markup_amount', TRUE);
							$markup = $markup ? $mt2mba_utility->format_option_markup($markup) : '';
						}

						$html .= PHP_EOL .
							'<option value="' . esc_attr($term->slug) . '"' . selected(sanitize_title($args['selected']), $term->slug, FALSE) . '>' .
							esc_html(apply_filters('woocommerce_variation_option_name', $term_name)) . esc_html($markup) . '</option>';
					}
				}
			} else {
				foreach ($options as $option) {
					// For non-taxonomy attributes, just use the option as is
					$html .= PHP_EOL .
						'<option value="' . esc_attr($option) . '"' .
						selected($args['selected'], sanitize_title($option), FALSE) . '>' .
						esc_html(apply_filters('woocommerce_variation_option_name', $option)) .
						'</option>';
				}
			}
		}
		
		// Close <SELECT> and return HTML
		return $html . PHP_EOL . '</select>';
	}
}	// END class MT2MBA_MARKUP_FRONTEND
?>