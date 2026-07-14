/**
 * Client-side validation for the term markup field.
 * Mirrors the server's validateMarkupValue() pattern: optional sign, a number
 * (dot or comma decimal), optional trailing %, or empty (clears the markup).
 * Invalid input blocks the save and flags the field with WordPress's own
 * form-invalid styling — same treatment core gives an empty term Name.
 *
 * @requires jQuery
 */
jQuery(document).ready(function($) {
	const MARKUP_PATTERN = /^[+-]?(\d+([.,]\d+)?|[.,]\d+)%?$/;
	const FIELDS = '#term_add_markup, #term_edit_markup';

	function markupIsValid($field) {
		const value = $field.val().trim();
		return value === '' || MARKUP_PATTERN.test(value);
	}

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
