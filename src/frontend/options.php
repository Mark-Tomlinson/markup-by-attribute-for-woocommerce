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
		add_filter('woocommerce_dropdown_variation_attribute_options_html', array($this, 'mt2mbaDropdownOptionsMarkupHTML'), 10, 2);
	}

	/**
	 * Add markups to the option dropdown HTML.
	 *
	 * @param	string	$html	The original dropdown HTML
	 * @param	array	$args {
	 * 		@type	string	$attribute			Attribute name
	 * 		@type	string	$name				Form field name
	 *  	@type	array	$options			Available options
	 *  	@type	string	$selected			Currently selected value
	 *  	@type	bool	$show_option_none	Whether to show "Choose an option"
	 * }
	 * @return string Modified dropdown HTML
	 */
	public function mt2mbaDropdownOptionsMarkupHTML($html, $args) {
		// Extract attribute from $args
		$attribute = $args['attribute'];

		// Check if this is a taxonomy (global) attribute
		if (taxonomy_exists($attribute)) {
			// Get attribute ID
			$attribute_id = wc_attribute_taxonomy_id_by_name($attribute);
			if ($attribute_id === 0) {
				error_log(sprintf(
					'Markup by Attribute: Failed to get taxonomy ID for global attribute %s',
					$attribute
				));
				return $html;
			}
		} else {
			// Not a taxonomy (global) attribute
			return $html;
		}

		// Exit early based on specific conditions
		if (
			// Don't overwrite if don't-overwrite-theme flag is set for the attribute
			get_option(DONT_OVERWRITE_THEME_PREFIX . $attribute_id) == 'yes' || 
			
			// Prevent duplicate markup in dropdowns if markup is already in the name
			get_option(REWRITE_TERM_NAME_PREFIX . wc_attribute_taxonomy_id_by_name($attribute)) == 'yes'
		) {
			return $html;
		}
		
		// Skip markups on variable products where all variations are zero-priced
		$strip_markups = false;
		if (
			$args['product'] && $args['product']->is_type('variable') && 
			$args['product']->get_variation_price('min') == 0 &&
			$args['product']->get_variation_price('max') == 0
		) {
			$strip_markups = true;
		}

		// Set globals
		global $mt2mba_utility;
	
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
							$markup = $markup ? $mt2mba_utility->formatOptionMarkup($markup) : '';
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