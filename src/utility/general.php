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
	 * Render a stored markup in the canonical notation, for the two admin fields
	 * that show it unformatted
	 *
	 * The attribute list's Markup column and the term edit field show the value
	 * exactly as stored, so a term last saved by an older version shows that
	 * version's notation ('+1.00') beside one saved today ('1'). Same markup,
	 * two spellings — and the column sorts as if they were different.
	 *
	 * Works on the stored '.'-decimal form, which is why it cannot reuse
	 * normalizeMarkupNotation() despite the '+' stripper sitting in it: that one
	 * reads the STORE'S separator, and in a comma-decimal store it would take the
	 * '.' in '+1.00' for a thousands separator and return 100.
	 *
	 * @since 4.8.0
	 * @param string $markup Markup exactly as held in term meta
	 * @return string        Canonical markup, localized for display
	 */
	public static function formatStoredMarkupForDisplay(string $markup): string {
		$markup = trim($markup);
		if ($markup === '') return '';

		$is_percentage = self::isPercentage($markup);
		$number = $is_percentage ? substr($markup, 0, -1) : $markup;

		// The canonical form validateMarkupValue() stores: the cast absorbs a
		// leading '+', the rtrim pair drops trailing zeros.
		$number = rtrim(rtrim(number_format((float) $number, MT2MBA_INTERNAL_PRECISION, '.', ''), '0'), '.');

		// A negative that rounds away leaves its sign behind, and '-0' in a field
		// reads as a bug.
		if ($number === '-0') $number = '0';

		// Localize the number alone; '%' is notation, not part of the decimal.
		return wc_format_localized_decimal($number) . ($is_percentage ? '%' : '');
	}

	/**
	 * Format a markup value as the annotation that decorates a term name or a
	 * drop-down option
	 *
	 * The form follows the markup TYPE, not where it is displayed:
	 *
	 *   percentage -> (Add 5%) / (Subtract 5%)   — a bare "-5%" reads ambiguously
	 *   currency   -> (+$5.00) / (-$5.00)        — the symbol already says what it is
	 *
	 * Deliberately knows nothing about the drop-down settings. Those belong to
	 * formatOptionMarkup(); the term-name path must not inherit them, or 'hide'
	 * would stop baking annotations into names that "Add Markup to Name?"
	 * governs, and the strip-symbol mode would wrongly remove the currency sign.
	 *
	 * @since 4.7.0
	 * @param string $markup Signed markup amount (string|float at runtime, cast to string)
	 * @return string        Annotation with its parentheses, or '' for no markup
	 */
	public static function formatMarkupAnnotation(string $markup): string {
		// Cast before comparing against zero. PHP 7 reads a non-numeric string's
		// leading digits in that comparison and PHP 8 does not; min-PHP is 7.4.
		if ($markup === '' || (float) $markup == 0) return '';

		$is_negative = (float) $markup < 0;

		if (self::isPercentage($markup)) {
			$pattern = $is_negative ? MT2MBA_MARKUP_NAME_PATTERN_SUBTRACT : MT2MBA_MARKUP_NAME_PATTERN_ADD;
			return sprintf($pattern, self::cleanUpPrice($markup));
		}

		return '(' . ($is_negative ? '-' : '+') . self::cleanUpPrice($markup) . ')';
	}

	/**
	 * Format the markup that appears in the options drop-down box
	 *
	 * A thin wrapper over formatMarkupAnnotation() that adds the two behaviors
	 * belonging to the drop-down alone: suppressing it entirely, and showing the
	 * amount without a currency symbol.
	 *
	 * @since 2.0.0
	 * @param string $markup Signed markup amount (string|float at runtime, cast to string)
	 * @return string        Formatted markup for dropdown display (e.g., " (+$5.00)")
	 */
	public static function formatOptionMarkup(string $markup): string {
		// Jump out if markup is not to be displayed.
		if (MT2MBA_DROPDOWN_BEHAVIOR == 'hide') return '';

		$annotation = self::formatMarkupAnnotation($markup);
		if ($annotation === '') return '';

		if (MT2MBA_DROPDOWN_BEHAVIOR != 'add') {
			// Drop the currency symbol, then close the gap it leaves behind. A
			// suffix-symbol locale ("1.234,50 kr") has a space in front of the
			// symbol that has to go with it. Only the sign form is touched —
			// the word form has no symbol to strip.
			$annotation = preg_replace(
				'/\(\s*([-+])\s*(.*?)\s*\)/u',
				'($1$2)',
				str_replace(MT2MBA_CURRENCY_SYMBOL, '', $annotation)
			);
		}

		return ' ' . $annotation;
	}

	/**
	 * Format the add and subtract line items that appears in the variation description
	 * @param	string	$markup		Signed markup amount (string|float at runtime, cast to string)
	 * @param	string	$attrb_name	Attribute name that the markup applies to
	 * @param	string	$term_name	Attribute term that the markup applies to
	 * @return	string				Formatted description
	 */
	public static function formatVariationMarkupDescription(string $markup, string $attrb_name, string $term_name): string {
		if ($markup != '' && $markup != 0) {
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

		if ($beginningPos === false || $endingPos === false) return trim($string);

		$textToDelete = substr($string, $beginningPos, ($endingPos + strlen($ending)) - $beginningPos);

		return trim(str_replace($textToDelete, '', $string));
	}

	/**
	 * Strip markup annotation from term name
	 *
	 * Removes markup annotations — the word form "(Add $5.00)" / "(Subtract 10%)" and
	 * the sign form "(+$5.00)" / "(-$5.00)" — from term names. Uses internationalized
	 * patterns to handle different languages and currency formats. This is used to
	 * clean term names before applying new markup annotations.
	 *
	 * Both forms are recognized because both exist in the wild: a name annotated
	 * before 4.7.0 keeps the word form until its term is next saved.
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

		// Sign form, anchored to the end of the string where addMarkupToName() puts
		// it; unanchored it would eat "Widget (-5) Blue". At least one digit is
		// required so "(+extras)" survives.
		$sign_pattern = '/\s*\([-+][0-9.,\s\p{Sc}\p{L}]*[0-9][0-9.,\s\p{Sc}\p{L}]*\)$/u';

		// Decoded HTML encoding
		$text = html_entity_decode($text);

		// Remove markup annotations
		$text = preg_replace($add_pattern, '', $text);
		$text = preg_replace($subtract_pattern, '', $text);
		$text = preg_replace($sign_pattern, '', $text);

		return trim($text);
	}

	/**
	 * Add markup annotation to term name
	 *
	 * Appends formatted markup notation to term names (e.g., "Blue (+$5.00)").
	 * Used when the plugin is configured to show markup in attribute option names.
	 *
	 * @since 3.9.0
	 * @param string $text   Base text
	 * @param string $markup Signed markup value (with % or currency)
	 * @return string        Text with markup annotation added
	 */
	public static function addMarkupToName(string $text, string $markup): string {
		$annotation = self::formatMarkupAnnotation($markup);

		return $annotation === '' ? $text : $text . ' ' . $annotation;
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
	 * Whichever character is NOT the store's decimal separator is a thousands
	 * separator and is dropped: "1.235,12" and "1,235.12" are each correct in one
	 * locale and meaningless in the other, so the separator cannot be guessed.
	 *
	 * IDEMPOTENT BY DESIGN — the decimal separator is NOT converted to '.'. The
	 * browser normalizes before submitting and the server normalizes again on
	 * arrival; a '.' introduced by the first pass would be read as a thousands
	 * separator by the second and stripped, storing 123512 for "1235,12". Convert
	 * with toInternalDecimal() once, at the point of storage or calculation.
	 *
	 * A rewriter, not a validator: anything it cannot make sense of comes back
	 * unchanged for validateMarkupValue() to reject. A trailing sign ("2-") is left
	 * alone on purpose so it fails validation rather than being guessed at.
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

		// A grouping mark after the decimal point ("1,235.12" in a comma store) is
		// malformed, not foreign: hand it back for rejection rather than stripping
		// it into 1.23512. Group sizes are not policed (Indian notation groups 2-2-3).
		$decimal_at = strpos($markup, $decimal_separator);
		$last_group_at = strrpos($markup, $thousands_separator);
		if ($decimal_at !== false && $last_group_at !== false && $last_group_at > $decimal_at) {
			return $raw;
		}

		// Drop thousands separators; the decimal separator stays (idempotency, above)
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
	 * The single definition of the percentage/fixed split. Not strpos() truthiness:
	 * a '%' at position 0 would read as false.
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
	 * ('.'-decimal). NOT idempotent: call it exactly once, where input enters the
	 * system. Fed its own output, a comma-decimal store would read the internal
	 * '.' as a thousands separator (see normalizeMarkupNotation()).
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

		// Rewrite locale notation into canonical form; the decimal separator stays
		// in the store's notation until toInternalDecimal() below
		$markup = self::normalizeMarkupNotation($markup);

		// Reject anything not canonically shaped BEFORE numeric parsing: "5abc"
		// would otherwise parse as 5 and "5%0" as 50. Thousands separators are gone,
		// so the one separator left, whichever it is, is the decimal point.
		// Identical to MARKUP_PATTERN in src/js/jq-mt2mba-validate-markup.js —
		// change both together.
		if (!preg_match('/^-?(\d+([.,]\d+)?|[.,]\d+)%?$/', $markup)) {
			return false;
		}

		// Determine markup type: percentage (ends with %) or fixed amount
		$is_percentage = self::isPercentage($markup);

		// Strip the % symbol, then move the decimal point to '.' for storage. Not
		// wc_format_decimal(): it strips unrecognized characters instead of rejecting them.
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
	//endregion

}	//	End class MT2MBA_UTILITY_GENERAL