/**
 * Handles dismissal of admin notifications for Markup by Attribute plugin.
 * Provides AJAX functionality to permanently dismiss plugin notices when
 * users click the dismiss button.
 *
 * @requires jQuery
 */
 (function($) {
	'use strict';
	$(function() {
		$('.mt2mba-notice').on('click', '.notice-dismiss', function(event, el) {
			var $notice = $(this).parent('.notice.is-dismissible');
			var dismiss_url = $notice.attr('data-dismiss-url');
			if (dismiss_url) {
				$.get(dismiss_url);
			}
		});
	});
})(jQuery);