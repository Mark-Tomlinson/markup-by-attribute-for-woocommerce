<?php
namespace mt2Tech\MarkupByAttribute\Utility;
use mt2Tech\MarkupByAttribute\Backend as Backend;

/**
 * Core utility functions for Markup-by-Attribute plugin
 *
 * Provides essential utility functions including database upgrades, price formatting,
 * markup validation and sanitization, text processing, and internationalization support.
 * This class serves as the foundation for all plugin operations.
 *
 * @package   mt2Tech\MarkupByAttribute\Utility
 * @author    Mark Tomlinson
 * @license   GPL-2.0+
 * @since     1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit();

class General {
	//region PROPERTIES
	/**
	 * Singleton instance
	 *
	 * @var self|null
	 */
	private static ?self $instance = null;
	//endregion

	//region INSTANCE MANAGEMENT
	/**
	 * Get singleton instance
	 *
	 * @return self Single instance of this class
	 */
	public static function get_instance(): self {
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
	public function __wakeup(): void {}

	// Private constructor
	private function __construct() {
		// Stamp schema version on first install (upgrades handled in mt2mba_run_upgrades)
		if (get_option('mt2mba_db_version', false) === false) {
			update_option('mt2mba_db_version', MT2MBA_SCHEMA_VERSION, false);
		}

		// Set global values used throughout the code
		if (!defined('MT2MBA_CURRENCY_SYMBOL')) {
			$settings = Backend\Settings::get_instance();
			define('MT2MBA_DESC_BEHAVIOR', get_option('mt2mba_desc_behavior', $settings->desc_behavior));
			define('MT2MBA_DROPDOWN_BEHAVIOR', get_option('mt2mba_dropdown_behavior', $settings->dropdown_behavior));
			define('MT2MBA_INCLUDE_ATTRB_NAME', get_option('mt2mba_include_attrb_name', $settings->include_attrb_name));
			define('MT2MBA_HIDE_BASE_PRICE', get_option('mt2mba_hide_base_price', $settings->hide_base_price));
			define('MT2MBA_SALE_PRICE_MARKUP', get_option('mt2mba_sale_price_markup', $settings->sale_price_markup));
			define('MT2MBA_ROUND_MARKUP', get_option('mt2mba_round_markup', $settings->round_markup));
			define('MT2MBA_MAX_VARIATIONS', get_option('mt2mba_max_variations', $settings->max_variations));
			define('MT2MBA_CURRENCY_SYMBOL', html_entity_decode(get_woocommerce_currency_symbol(get_woocommerce_currency())));
		}
	}
	//endregion

	//region FORMATTING METHODS
	/**
	 * Clean up the price or markup and reformat according to currency options
	 *
	 * Formats numeric values for display, handling both percentage and currency amounts.
	 * Uses WooCommerce's currency formatting for consistency with store settings.
	 *
	 * @since 2.0.0
	 * @param string $text A number that will be reformatted into the local currency
	 * @return string      Properly formatted price with currency indicator
	 */
	public function cleanUpPrice(string $text): string {
		// Extract amount from string and set to absolute
		$amount = abs(floatval($text));

		if (strpos($text, "%")) {		// Text is a percentage?
			// Amount, trimmed and percent symbol added
			return trim($amount . '%');
		} else {						// Text is an amount
			// Amount formatted as local currency, no HTML tags, HTML decoded, and trimmed
			return trim(html_entity_decode(strip_tags(wc_price($amount))));
		}
	}

	/**
	 * Format the markup that appears in the options drop-down box
	 *
	 * Creates formatted markup text for display in WooCommerce variation dropdowns.
	 * Handles plugin settings for showing/hiding markup, currency symbols, and formatting.
	 *
	 * @since 2.0.0
	 * @param string $markup Signed markup amount (string|float at runtime, cast to string)
	 * @return string        Formatted markup for dropdown display (e.g., " (+$5.00)")
	 */
	public function formatOptionMarkup(string $markup): string {
		if ($markup != "" && $markup != 0) {
			// Jump out if markup is not to be displayed.
			if (MT2MBA_DROPDOWN_BEHAVIOR == 'hide') {
				return '';
			}

			// Set sign
			$sign = (float) $markup < 0 ? "-" : "+";
			// There are instances where the markup for the product is not in the database.
			// Where this is the case and the markup is a percentage, show only the percentage.
			if (strpos($markup, '%')) {
				// Return formatted with percentage
				$markup = trim(html_entity_decode($markup));
			} elseif (MT2MBA_DROPDOWN_BEHAVIOR == 'add') {
				// Return formatted with symbol
				$markup = html_entity_decode($sign . $this->cleanUpPrice($markup));
			} else {
				// Return formatted without symbol
				$markup = html_entity_decode($sign . trim(str_replace(MT2MBA_CURRENCY_SYMBOL, "", $this->cleanUpPrice($markup))));
			}
			return " (" . $markup . ")";
		}
		// No markup; return empty string
		return '';
	}

	/**
	 * Format the add and subtract line items that appears in the variation description
	 * @param	string	$markup		Signed markup amount (string|float at runtime, cast to string)
	 * @param	string	$attrb_name	Attribute name that the markup applies to
	 * @param	string	$term_name	Attribute term that the markup applies to
	 * @return	string				Formatted description
	 */
	public function formatVariationMarkupDescription(string $markup, string $attrb_name, string $term_name): string {
		if ($markup != "" && $markup != 0) {
			// Clean any existing markup from the term name before formatting
			$term_name = $this->stripMarkupAnnotation($term_name);

			// Sanitize inputs for safe display (but preserve text content)
			$term_name = sanitize_text_field($term_name);
			$attrb_name = sanitize_text_field($attrb_name);

			// Two different translation strings based on whether attribute name is included
			if (MT2MBA_INCLUDE_ATTRB_NAME == 'yes') {
				// Translators; %1$s is the formatted price, %2$s is the attribute name, %3$s is the term name
				$desc_format = (float) $markup < 0 ?
					__('Subtract %1$s for %2$s: %3$s', 'markup-by-attribute-for-woocommerce') :
					__('Add %1$s for %2$s: %3$s', 'markup-by-attribute-for-woocommerce');

				return html_entity_decode(
					sprintf(
						$desc_format,
						esc_html($this->cleanUpPrice($markup)),
						esc_html($attrb_name),
						esc_html($term_name)
					)
				);
			} else {				// Translators; %1$s is the formatted price, %2$s is the term name
				$desc_format = (float) $markup < 0 ?
					__('Subtract %1$s for %2$s', 'markup-by-attribute-for-woocommerce') :
					__('Add %1$s for %2$s', 'markup-by-attribute-for-woocommerce');

				return html_entity_decode(
					sprintf(
						$desc_format,
						esc_html($this->cleanUpPrice($markup)),
						esc_html($term_name)
					)
				);
			}
		}
		// No markup; return empty string
		return '';
	}
	//endregion

	//region STRING UTILITIES
	/**
	 * Remove bracketed substring from string
	 *
	 * Removes text between specified markers from a string. Used primarily to
	 * strip markup descriptions from variation descriptions when prices are cleared.
	 * The method handles cases where markers are not found gracefully.
	 *
	 * @since 2.0.0
	 * @param string $beginning Marker at the beginning of the string to be removed
	 * @param string $ending    Marker at the ending of the string to be removed
	 * @param string $string    The string to be processed
	 * @return string           The string minus the text to be removed and the beginning and ending markers
	 */
	public function removeBracketedString(string $beginning, string $ending, string $string): string {
		$beginningPos = strpos($string, $beginning, 0);
		$endingPos = strpos($string, $ending, $beginningPos);

		if ($beginningPos === FALSE || $endingPos === FALSE) return trim($string);

		$textToDelete = substr($string, $beginningPos, ($endingPos + strlen($ending)) - $beginningPos);

		return trim(str_replace($textToDelete, '', $string));
	}

	/**
	 * Strip markup annotation from term name
	 *
	 * Removes markup annotations (like "(Add $5.00)" or "(Subtract 10%)") from term names.
	 * Uses internationalized patterns to handle different languages and currency formats.
	 * This is used to clean term names before applying new markup annotations.
	 *
	 * @since 3.9.0
	 * @param string $text The text to process
	 * @return string      Text with markup annotation removed
	 */
	public function stripMarkupAnnotation(string $text): string {
		// Pattern for numbers that handles international formats
		$number_pattern = '[0-9.,\s%\p{Sc}A-Z]*';

		// Convert Add and Subtract constants to regex with international number pattern
		$add_pattern = '/(?:^|\s)' . str_replace('%s', $number_pattern, preg_quote(MT2MBA_MARKUP_NAME_PATTERN_ADD)) . '/u';
		$subtract_pattern = '/(^|\s)' . str_replace('%s', $number_pattern, preg_quote(MT2MBA_MARKUP_NAME_PATTERN_SUBTRACT)) . '/u';

		// Decoded HTML encoding
		$text = html_entity_decode($text);

		// Remove markup annotations
		$text = preg_replace($add_pattern, '', $text);
		$text = preg_replace($subtract_pattern, '', $text);

		return trim($text);
	}

	/**
	 * Add markup annotation to term name
	 *
	 * Appends formatted markup notation to term names (e.g., "Blue (Add $5.00)").
	 * Used when the plugin is configured to show markup in attribute option names.
	 *
	 * @since 3.9.0
	 * @param string $text        Base text
	 * @param string $markup      Markup value (with % or currency)
	 * @param bool   $is_negative Whether this is a negative markup
	 * @return string             Text with markup annotation added
	 */
	public function addMarkupToName(string $text, string $markup, bool $is_negative = false): string {
		// Format the markup value using cleanUpPrice()
		$formatted_markup = $this->cleanUpPrice($markup);

		$pattern = $is_negative ? MT2MBA_MARKUP_NAME_PATTERN_SUBTRACT : MT2MBA_MARKUP_NAME_PATTERN_ADD;
		return $text . " " . sprintf($pattern, $formatted_markup);
	}

	/**
	 * Add markup annotation to term description
	 *
	 * Appends formatted markup notation to term descriptions. Used when the plugin
	 * is configured to show markup information in attribute term descriptions.
	 *
	 * @since 3.9.0
	 * @param string $description Base description text
	 * @param string $markup      Markup value (with % or currency)
	 * @param bool   $is_negative Whether this is a negative markup
	 * @return string             Description with markup annotation added
	 */
	public function addMarkupToTermDescription(string $description, string $markup, bool $is_negative = false): string {
		// Format the markup value using cleanUpPrice()
		$formatted_markup = $this->cleanUpPrice($markup);

		$pattern = $is_negative ? MT2MBA_MARKUP_NAME_PATTERN_SUBTRACT : MT2MBA_MARKUP_NAME_PATTERN_ADD;
		return trim($description . "\n" . trim(sprintf($pattern, $formatted_markup)));
	}
	//endregion

	//region VALIDATION & SANITIZATION
	/**
	 * Validate and sanitize markup value input
	 *
	 * @param	string	$markup		Raw markup input
	 * @return	string|false		Validated markup or false if invalid
	 */
	public function validateMarkupValue(string $markup) {
		// Handle empty values - treat zero as empty markup (no price change)
		if (empty($markup) || $markup === '0' || $markup === 0) {
			return '';
		}

		// Sanitize input - remove any HTML tags and trim whitespace
		$markup = sanitize_text_field(trim($markup));

		// Remove any non-standard whitespace characters
		$markup = preg_replace('/\s+/', '', $markup);

		// Determine markup type: percentage (ends with %) or fixed amount
		$is_percentage = (substr($markup, -1) === '%');

		if ($is_percentage) {
			// Strip the % symbol to validate just the numeric portion
			$numeric_part = substr($markup, 0, -1);
		} else {
			// Fixed amount - validate the entire string as numeric
			$numeric_part = $markup;
		}

		// Convert localized decimal input to standardized format using WooCommerce
		$numeric_part = wc_format_decimal($numeric_part, false, true);

		// Validate numeric format using regex pattern
		// Pattern breakdown: ^[+-]?(?:\d+(?:\.\d+)?|\d*\.\d+)$
		// ^[+-]? = optional plus or minus at start
		// (?:...|...) = non-capturing group with two alternatives:
		//   \d+(?:\.\d+)? = one or more digits, optionally followed by decimal and one or more digits
		//   \d*\.\d+ = zero or more digits, required decimal point, one or more digits (for .5, .25, etc.)
		if (!preg_match('/^[+-]?(?:\d+(?:\.\d+)?|\d*\.\d+)$/', $numeric_part)) {
			return false;
		}

		// Convert to float for range validation and formatting
		$numeric_value = floatval($numeric_part);

		// Format validated markup value based on type
		if ($is_percentage) {
			// Format percentage with maximum precision, truncating trailing zeros
			return rtrim(rtrim(number_format($numeric_value, MT2MBA_INTERNAL_PRECISION, '.', ''), '0'), '.') . '%';
		} else {
			// Return formatted fixed amount, truncating trailing zeros
			return rtrim(rtrim(number_format($numeric_value, MT2MBA_INTERNAL_PRECISION, '.', ''), '0'), '.');
		}
	}

	/**
	 * Sanitize markup value for safe database storage
	 *
	 * @param	string	$markup		Markup value to sanitize
	 * @return	string				Sanitized markup value
	 */
	public function sanitizeMarkupForStorage(string $markup): string {
		// First validate the markup
		$validated = $this->validateMarkupValue($markup);
		if ($validated === false) {
			return '';
		}

		// Additional sanitization for database storage
		return sanitize_text_field($validated);
	}

	/**
	 * Sanitize markup value for safe output display
	 *
	 * @param	string	$markup		Markup value to sanitize
	 * @return	string				Sanitized markup value for display
	 */
	public function sanitizeMarkupForDisplay(string $markup): string {
		// Sanitize for HTML output
		return esc_html(sanitize_text_field($markup));
	}
	//endregion

}	//	End class MT2MBA_UTILITY_GENERAL