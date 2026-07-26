/**
 * Client-side validation for the term markup field.
 *
 * normalizeMarkupNotation() below is a line-for-line mirror of the PHP method of
 * the same name in src/utility/general.php — the value is rewritten into canonical
 * form ([-]digits[.digits][%]) before it is tested, exactly as the server does.
 * Both implementations are held to the same input table by tests/test-08; if you
 * change one, change the other and update that table.
 *
 * Normalizing before testing is the whole point: the previous version tested the
 * raw value against the canonical pattern, so "5 %" — which WooCommerce accepts
 * and the server normalizes happily — was rejected here before it ever got sent.
 *
 * Invalid input blocks the save and flags the field with WordPress's own
 * form-invalid styling — same treatment core gives an empty term Name.
 *
 * @requires jQuery
 */
jQuery(document).ready(function($) {
	// Deliberately identical to the pattern in Utility\General::validateMarkupValue().
	// Thousands separators are stripped before the test, so at most one separator
	// remains and either character is acceptable as the decimal point.
	const MARKUP_PATTERN = /^-?(\d+([.,]\d+)?|[.,]\d+)%?$/;

	// The store's decimal separator, localized from PHP. Whichever character is
	// NOT the decimal separator is a thousands separator: "1.235,12" is correct in
	// a comma store and meaningless in a dot store, so this cannot be guessed.
	const DECIMAL_SEPARATOR =
		(window.mt2mbaMarkup && window.mt2mbaMarkup.decimalSeparator) || '.';
	const FIELDS = '#term_add_markup, #term_edit_markup';

	// Mirror of Utility\General::normalizeMarkupNotation()
	function normalizeMarkupNotation(raw) {
		// Unify percent sign variants on U+0025
		let markup = raw.replace(/[\u066A\uFF05\uFE6A]/g, '%');

		// Strip every kind of space, including NBSP and the narrow/figure spaces
		// used as thousands separators
		markup = markup.replace(/[\s\u00A0\u202F\u2007]+/g, '');
		if (markup === '') return raw;

		// Lift a leading percent sign, with or without a sign ahead of it
		let leadingPercent = false;
		const leading = markup.match(/^([+-]?)%([\s\S]*)$/);
		if (leading) {
			leadingPercent = true;
			markup = leading[1] + leading[2];
		}

		// ...and a trailing one
		let trailingPercent = false;
		if (markup.slice(-1) === '%') {
			trailingPercent = true;
			markup = markup.slice(0, -1);
		}

		// A percent sign at both ends is malformed; hand it back for rejection
		if (leadingPercent && trailingPercent) return raw;

		// Positive is implied
		if (markup.charAt(0) === '+') markup = markup.slice(1);

		const thousandsSeparator = DECIMAL_SEPARATOR === ',' ? '.' : ',';

		// A grouping mark can only appear ahead of the decimal point. In a
		// comma-decimal store "1,235.12" puts it after, which is malformed rather
		// than merely foreign — hand it back for rejection instead of stripping it
		// into 1.23512. Group SIZES are not policed: 3-digit grouping is not
		// universal (Indian notation groups 2-2-3).
		const decimalAt = markup.indexOf(DECIMAL_SEPARATOR);
		const lastGroupAt = markup.lastIndexOf(thousandsSeparator);
		if (decimalAt !== -1 && lastGroupAt !== -1 && lastGroupAt > decimalAt) return raw;

		// Drop thousands separators. The decimal separator stays in the store's
		// notation: this value goes into the field the user is looking at and is
		// then re-normalized server-side, so it must stay in their locale and this
		// function must be idempotent.
		markup = markup.split(thousandsSeparator).join('');

		return markup + ((leadingPercent || trailingPercent) ? '%' : '');
	}

	function markupIsValid($field) {
		const value = normalizeMarkupNotation($field.val().trim());
		return value === '' || MARKUP_PATTERN.test(value);
	}

	// Rewrite the field to canonical form once the user leaves it, so the rule is
	// visible rather than merely enforced ("5 %" becomes "5%" in front of them)
	$(document).on('blur', FIELDS, function() {
		const raw = $(this).val().trim();
		if (raw === '') return;
		const normalized = normalizeMarkupNotation(raw);
		if (normalized !== raw && MARKUP_PATTERN.test(normalized)) {
			$(this).val(normalized);
		}
	});

	// Own class, styled in admin-style.css. Core's red-border rule would need
	// form-required on the row, and that class makes WP's submit validation
	// reject a blank markup — but blank is valid (it clears the markup).
	function flagInvalid($field) {
		$field.closest('.form-field').addClass('mt2mba-invalid');
		$field.trigger('focus');
	}

	// Clear the flag as soon as the user edits the field
	$(document).on('input', FIELDS, function() {
		$(this).closest('.form-field').removeClass('mt2mba-invalid');
	});

	// Edit form (term.php) is a normal POST — intercept the submit
	$('#edittag').on('submit', function(event) {
		const $field = $(this).find('#term_edit_markup');
		if ($field.length && !markupIsValid($field)) {
			flagInvalid($field);
			event.preventDefault();
			event.stopImmediatePropagation();
		}
	});

	// Add form (edit-tags.php) saves via AJAX: core's tags.js acts on the
	// submit button's click and suppresses the real submit event, so a submit
	// handler never runs. A capture-phase listener fires before tags.js can.
	document.addEventListener('click', function(event) {
		const button = event.target.closest('#addtag #submit');
		if (!button) return;
		const $field = $('#term_add_markup');
		if ($field.length && !markupIsValid($field)) {
			flagInvalid($field);
			event.preventDefault();
			event.stopPropagation();
		}
	}, true);
});
