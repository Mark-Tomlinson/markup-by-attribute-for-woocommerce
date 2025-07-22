<?php
namespace mt2Tech\MarkupByAttribute\Frontend;

/**
 * Frontend dropdown options handler for WooCommerce variation attributes
 * 
 * Manages the display of markup pricing information in WooCommerce product
 * variation dropdowns. Handles complex logic for theme compatibility,
 * plugin settings, and price display formatting.
 *
 * @package   mt2Tech\MarkupByAttribute\Frontend
 * @author    Mark Tomlinson
 * @license   GPL-2.0+
 * @since     1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit();

class Options {
	//region PROPERTIES
	/**
	 * Singleton instance
	 * 
	 * @var self|null
	 */
	private static $instance = null;
	//endregion

	//region INSTANCE MANAGEMENT
	/**
	 * Get singleton instance
	 * 
	 * @return self Single instance of this class
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
	 * Initialize WordPress hooks
	 * 
	 * Sets up filter to modify WooCommerce variation dropdown HTML.
	 */
	private function __construct() {
		add_filter('woocommerce_dropdown_variation_attribute_options_html', array($this, 'mt2mbaDropdownOptionsMarkupHTML'), 10, 2);
	}
	//endregion

	//region HOOKS & CALLBACKS
	/**
	 * Add markups to the option dropdown HTML
	 * 
	 * Replaces WooCommerce's default variation dropdown with one that includes markup pricing.
	 * Handles complex logic for when to show markups based on plugin settings, theme compatibility,
	 * and product pricing states. Only processes global (taxonomy) attributes.
	 *
	 * @since 1.0.0
	 * @param string $html The original dropdown HTML
	 * @param array  $args {
	 *     @type string $attribute        Attribute name
	 *     @type string $name             Form field name
	 *     @type array  $options          Available options
	 *     @type string $selected         Currently selected value
	 *     @type bool   $show_option_none Whether to show "Choose an option"
	 * }
	 * @return string Modified dropdown HTML with markup information
	 */
	public function mt2mbaDropdownOptionsMarkupHTML($html, $args) {
		// Extract attribute from $args
		$attribute = $args['attribute'];

		// Only process global (taxonomy) attributes - local attributes don't support markups
		if (taxonomy_exists($attribute)) {
			// Get attribute ID for option lookups
			$attribute_id = wc_attribute_taxonomy_id_by_name($attribute);
			if ($attribute_id === 0) {
				error_log(sprintf(
					'Markup by Attribute: Failed to get taxonomy ID for global attribute %s',
					$attribute
				));
				return $html;
			}
		} else {
			// Not a global attribute - return original HTML unchanged
			return $html;
		}

		// Exit early based on plugin configuration and compatibility settings
		if (
			// Don't overwrite if admin configured this attribute to preserve theme styling
			get_option(DONT_OVERWRITE_THEME_PREFIX . $attribute_id) == 'yes' || 
			
			// Prevent duplicate markup display if markup is already included in term names
			get_option(REWRITE_TERM_NAME_PREFIX . wc_attribute_taxonomy_id_by_name($attribute)) == 'yes'
		) {
			return $html;
		}
		
		// Determine if markups should be stripped for zero-priced products
		$strip_markups = false;
		if (
			$args['product'] && $args['product']->is_type('variable') && 
			$args['product']->get_variation_price('min') == 0 &&
			$args['product']->get_variation_price('max') == 0
		) {
			// Skip markup display on products where all variations are zero-priced
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
	
		// Build individual <OPTION> elements within the <SELECT>
		if (!empty($options)) {
			if ($product && taxonomy_exists($attribute)) {  // product exists and attribute is global
				// Get all attribute terms for this product
				$terms = wc_get_product_terms($product->get_id(), $attribute, array('fields' => 'all'));
				foreach ($terms as $term) {
					// Only include terms that are actually used in this product's variations
					if (in_array($term->slug, $options)) {
						if ($strip_markups) {
							// Remove markup annotations for zero-priced products
							$term_name = $mt2mba_utility->stripMarkupAnnotation($term->name);
							$markup = '';
						} else {
							// Get and format markup for display in dropdown
							$term_name = $mt2mba_utility->sanitizeMarkupForDisplay($term->name);
							$raw_markup = get_metadata('post', $product->get_id(), 'mt2mba_' . $term->term_id . '_markup_amount', TRUE);
							
							// Sanitize and format markup for display
							if ($raw_markup) {
								$sanitized_markup = $mt2mba_utility->sanitizeMarkupForDisplay($raw_markup);
								$markup = $mt2mba_utility->formatOptionMarkup($sanitized_markup);
							} else {
								$markup = '';
							}
						}

						// Build the option element with proper escaping
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
	//endregion

}	// END class MT2MBA_MARKUP_FRONTEND
?>