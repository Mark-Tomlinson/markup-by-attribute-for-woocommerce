/**
 * WordPress pointer management for Markup by Attribute admin interface.
 * Handles display and dismissal of WordPress admin pointers (tooltips/hints)
 * for plugin onboarding and feature discovery.
 *
 * @requires jQuery
 * @requires mt2mbaPointer (localized script data)
 */
jQuery(document).ready(function($) {
	$(mt2mbaPointer.pointers).each(function(i) {
		mt2mba_open_pointer(i);
	});

	function mt2mba_open_pointer(i) {
		pointer = mt2mbaPointer.pointers[i];
		options = $.extend(pointer.options, {
			close: function() {
				$.post(ajaxurl, {
					pointer: pointer.pointer_id,
					action: 'dismiss-wp-pointer'
				});
			}
		});
		$(pointer.target).pointer(options).pointer('open');
	}
});