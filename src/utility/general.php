<?php
namespace mt2Tech\MarkupByAttribute\Utility;

/**
 * Stateless string helpers for Markup-by-Attribute
 *
 * Price formatting, markup validation and sanitization, and the markup annotation
 * that decorates term names and variation descriptions. All methods are static —
 * plugin bootstrap (constants, schema stamping) lives in the main plugin file.
 *
 * @package   mt2Tech\MarkupByAttribute\Utility
 * @author    Mark Tomlinson
 * @license   GPL-3.0-or-later
 * @since     1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) exit();

class General {
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
	public static function cleanUpPrice(string $text): string {
		// Extract amount from string and set to absolute
		$amount = abs(floatval($text));

		if (self::isPercentage($text)) {
			// Amount, trimmed and percent symbol added
			return trim($amount . '%');
		} else {
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
	public static function formatOptionMarkup(string $markup): string {
		if ($markup != "" && $markup != 0) {
			// Jump out if markup is not to be displayed.
			if (MT2MBA_DROPDOWN_BEHAVIOR == 'hide') {
				return '';
			}

			// Set sign
			$sign = (float) $markup < 0 ? "-" : "+";
			// There are instances where the markup for the product is not in the database.
			// Where this is the case and the markup is a percentage, show only the percentage.
			if (self::isPercentage($markup)) {
				// Return formatted with percentage
				$markup = trim(html_entity_decode($markup));
			} elseif (MT2MBA_DROPDOWN_BEHAVIOR == 'add') {
				// Return formatted with symbol
				$markup = html_entity_decode($sign . self::cleanUpPrice($markup));
			} else {
				// Return formatted without symbol
				$markup = html_entity_decode($sign . trim(str_replace(MT2MBA_CURRENCY_SYMBOL, "", self::cleanUpPrice($markup))));
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
	public static function formatVariationMarkupDescription(string $markup, string $attrb_name, string $term_name): string {
		if ($markup != "" && $markup != 0) {
			// Clean any existing markup from the term name before formatting
			$term_name = self::stripMarkupAnnotation($term_name);

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
						esc_html(self::cleanUpPrice($markup)),
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
						esc_html(self::cleanUpPrice($markup)),
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
	public static function removeBracketedString(string $beginning, string $ending, string $string): string {
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
	public static function stripMarkupAnnotation(string $text): string {
		// Pattern for numbers that handles international formats
		$number_pattern = '[0-9.,\s%\p{Sc}A-Z]*';

		// Convert Add and Subtract constants to regex with international number pattern
		$add_pattern = '/(?:^|\s)' . str_replace('%s', $number_pattern, preg_quote(MT2MBA_MARKUP_NAME_PATTERN_ADD, '/')) . '/u';
		$subtract_pattern = '/(^|\s)' . str_replace('%s', $number_pattern, preg_quote(MT2MBA_MARKUP_NAME_PATTERN_SUBTRACT, '/')) . '/u';

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
	public static function addMarkupToName(string $text, string $markup, bool $is_negative = false): string {
		// Format the markup value using cleanUpPrice()
		$formatted_markup = self::cleanUpPrice($markup);

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
	public static function addMarkupToTermDescription(string $description, string $markup, bool $is_negative = false): string {
		// Format the markup value using cleanUpPrice()
		$formatted_markup = self::cleanUpPrice($markup);

		$pattern = $is_negative ? MT2MBA_MARKUP_NAME_PATTERN_SUBTRACT : MT2MBA_MARKUP_NAME_PATTERN_ADD;
		return trim($description . "\n" . trim(sprintf($pattern, $formatted_markup)));
	}
	//endregion

	//region VALIDATION & SANITIZATION
	/**
	 * Rewrite a markup value into canonical notation
	 *
	 * Locale-canonical form is [-]digits[<sep>digits][%]: no whitespace, no
	 * thousands separators, sign leading only, percent sign U+0025 trailing only,
	 * and the decimal separator left in the STORE'S notation. Examples for a store
	 * whose decimal separator is a comma:
	 *
	 *   "5 %"       -> "5%"       space before the percent sign (WooCommerce allows it)
	 *   "%50"       -> "50%"      leading percent sign (Turkish convention)
	 *   "-%50"      -> "-50%"     ...with a sign
	 *   "50٪"       -> "50%"      U+066A Arabic percent
	 *   "50％"      -> "50%"      U+FF05 fullwidth percent
	 *   "1235,12"   -> "1235,12"  comma decimal, already canonical
	 *   "1 235,12"  -> "1235,12"  space as thousands separator (French)
	 *   "1.235,12"  -> "1235,12"  dot as thousands separator (German, Spanish)
	 *   "+5"        -> "5"        positive is implied
	 *
	 * Separator handling needs the store's configured decimal separator, because
	 * "1.235,12" and "1,235.12" are each correct in some locale and meaningless in
	 * the other. Whichever character is NOT the decimal separator is treated as a
	 * thousands separator and dropped.
	 *
	 * IMPORTANT — the decimal separator is deliberately NOT converted to '.' here.
	 * This method has to be idempotent, because the browser normalizes the field
	 * before submitting and the server normalizes again on arrival. Converting
	 * "1235,12" to "1235.12" here would make that second pass read the '.' as a
	 * thousands separator and strip it, storing 123512. Use toInternalDecimal()
	 * once, at the point of storage or calculation, instead.
	 *
	 * This is a rewriter, not a validator: anything it cannot make sense of comes
	 * back unchanged for validateMarkupValue() to reject. A trailing sign ("2-")
	 * is deliberately left alone so it fails validation — WooCommerce silently
	 * ignores it and treats "2-" as +2, which is worth an error rather than a guess.
	 *
	 * @since 4.7.0
	 * @param string      $raw               Markup value as entered
	 * @param string|null $decimal_separator Store's decimal separator; defaults to
	 *                                       WooCommerce's configured one
	 * @return string                        Canonical notation, or $raw unchanged
	 *                                       if unrecognizable
	 */
	public static function normalizeMarkupNotation(string $raw, ?string $decimal_separator = null): string {
		if ($decimal_separator === null) {
			$decimal_separator = function_exists('wc_get_price_decimal_separator')
				? wc_get_price_decimal_separator()
				: '.';
		}

		// Unify percent sign variants on U+0025
		$markup = str_replace(["\u{066A}", "\u{FF05}", "\u{FE6A}"], '%', $raw);

		// Strip every kind of space, including NBSP and the narrow/figure spaces
		// used as thousands separators
		$markup = preg_replace('/[\s\x{00A0}\x{202F}\x{2007}]+/u', '', $markup);
		if ($markup === null || $markup === '') return $raw;

		// Lift a leading percent sign, with or without a sign ahead of it
		$leading_percent = false;
		if (preg_match('/^([+-]?)%(.*)$/', $markup, $matches)) {
			$leading_percent = true;
			$markup = $matches[1] . $matches[2];
		}

		// ...and a trailing one
		$trailing_percent = false;
		if (substr($markup, -1) === '%') {
			$trailing_percent = true;
			$markup = substr($markup, 0, -1);
		}

		// A percent sign at both ends is malformed; hand it back for rejection
		if ($leading_percent && $trailing_percent) return $raw;

		// Positive is implied
		if (substr($markup, 0, 1) === '+') $markup = substr($markup, 1);

		$thousands_separator = ($decimal_separator === ',') ? '.' : ',';

		// A grouping mark can only appear ahead of the decimal point. In a
		// comma-decimal store "1,235.12" puts it after, which is malformed rather
		// than merely foreign — hand it back for rejection instead of stripping it
		// into 1.23512. Group SIZES are not policed: 3-digit grouping is not
		// universal (Indian notation groups 2-2-3).
		$decimal_at = strpos($markup, $decimal_separator);
		$last_group_at = strrpos($markup, $thousands_separator);
		if ($decimal_at !== false && $last_group_at !== false && $last_group_at > $decimal_at) {
			return $raw;
		}

		// Drop thousands separators. The decimal separator is deliberately left in
		// the store's notation — see the note on idempotency above.
		$markup = str_replace($thousands_separator, '', $markup);

		return $markup . (($leading_percent || $trailing_percent) ? '%' : '');
	}

	/**
	 * Convert a locale-canonical number to the internal '.'-decimal form
	 *
	 * Call once, at the point of storage or calculation, on a value that has
	 * already been through normalizeMarkupNotation().
	 *
	 * @since 4.7.0
	 * @param string      $number            Locale-canonical number, no thousands separators
	 * @param string|null $decimal_separator Store's decimal separator; defaults to
	 *                                       WooCommerce's configured one
	 * @return string                        The same number with a '.' decimal point
	 */
	public static function toInternalDecimal(string $number, ?string $decimal_separator = null): string {
		if ($decimal_separator === null) {
			$decimal_separator = function_exists('wc_get_price_decimal_separator')
				? wc_get_price_decimal_separator()
				: '.';
		}
		return str_replace($decimal_separator, '.', $number);
	}

	/**
	 * Is this markup a percentage rather than a fixed amount?
	 *
	 * The single definition of the percentage/fixed split. Callers used to test
	 * this with the truthiness of strpos($markup, '%'), which is only correct by
	 * accident of data shape — a '%' at position 0 reads as false.
	 *
	 * @since 4.7.0
	 * @param string $markup Markup value
	 * @return bool          True when the markup is a percentage
	 */
	public static function isPercentage(string $markup): bool {
		return substr(trim($markup), -1) === '%';
	}

	/**
	 * Validate and sanitize markup value input
	 *
	 * Takes a value in the STORE'S notation and returns it in INTERNAL notation
	 * ('.'-decimal). Those are different alphabets, so this is not idempotent and
	 * must be called exactly once, at the point input enters the system. Feeding
	 * its own output back in re-reads the internal '.' as a thousands separator in
	 * a comma-decimal store — that is how "1235,12" once became 123512.
	 *
	 * @param	string	$markup		Raw markup input, in the store's notation
	 * @return	string|false		Validated markup in internal notation, or false
	 */
	public static function validateMarkupValue(string $markup) {
		// Handle empty values - treat zero as empty markup (no price change)
		if (empty($markup) || $markup === '0' || $markup === 0) {
			return '';
		}

		// Sanitize input - remove any HTML tags and trim whitespace
		$markup = sanitize_text_field(trim($markup));

		// Rewrite locale notation into canonical form: strips whitespace and
		// thousands separators, unifies percent sign variants, moves a leading
		// percent sign to the back, and puts the decimal point on '.'
		$markup = self::normalizeMarkupNotation($markup);

		// Reject anything not canonically shaped. This has to happen before any
		// numeric parsing: wc_format_decimal() strips characters it does not
		// recognize rather than failing on them, so without this check "5abc"
		// parses as 5 and "5%0" as 50 — both silently wrong prices.
		//
		// Thousands separators are gone by this point, so at most one separator can
		// remain and either character is acceptable — whichever one it is, it is
		// the decimal point. Deliberately identical to MARKUP_PATTERN in
		// src/js/jq-mt2mba-validate-markup.js; change both together.
		if (!preg_match('/^-?(\d+([.,]\d+)?|[.,]\d+)%?$/', $markup)) {
			return false;
		}

		// Determine markup type: percentage (ends with %) or fixed amount
		$is_percentage = self::isPercentage($markup);

		// Strip the % symbol, then move the decimal point to '.' for storage. No
		// call to wc_format_decimal() — it strips unrecognized characters rather
		// than rejecting them, which is how "5abc" used to store as 5.
		$numeric_part = $is_percentage ? substr($markup, 0, -1) : $markup;
		$numeric_part = self::toInternalDecimal($numeric_part);

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
	public static function sanitizeMarkupForStorage(string $markup): string {
		// First validate the markup
		$validated = self::validateMarkupValue($markup);
		if ($validated === false) {
			return '';
		}

		// Additional sanitization for database storage
		return sanitize_text_field($validated);
	}

	// sanitizeMarkupForDisplay() lived here. It was esc_html(sanitize_text_field())
	// — an output escaper with a sanitizer's name — and all three callers escaped
	// its result again. Escaping now happens once, at each point of output.
	//endregion

}	//	End class MT2MBA_UTILITY_GENERAL