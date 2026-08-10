/**
 * Handles markup recalculation from the WooCommerce product list page.
 * Provides both individual and bulk markup reapplication functionality
 * with visual feedback and progress indicators.
 *
 * @requires jQuery
 * @requires mt2mbaListLocal (localized script data)
 */
jQuery(document).ready(function($) {
	// Set hover titles from data attributes (single pass, no per-row inline scripts)
	$('.js-mt2mba-reapply-markup').each(function() {
		var basePrice = $(this).data('base-price');
		if (basePrice) {
			$(this).attr('title',
				mt2mbaListLocal.i18n.reapplyTitle.replace('%s', basePrice)
			);
		}
	});

	// Process bulk reapply if needed
	const urlParams = new URLSearchParams(window.location.search);
	const bulkIds = urlParams.get('reapply_markups_ids');
	if (bulkIds) {
		// Drop the parameter before processing starts. It rides on WooCommerce's own
		// redirect, so only this one key is removed and the rest of the query string
		// (post_type, paged, filters) survives. Without this, a refresh -- or the back
		// button, or a bookmarked URL -- silently repeats the entire bulk reprice.
		urlParams.delete('reapply_markups_ids');
		const query = urlParams.toString();
		history.replaceState(null, '',
			window.location.pathname + (query ? '?' + query : '') + window.location.hash);

		const productIds = bulkIds.split(',');
		processBulkReapply(productIds);
	}

	// Handle clicks on individual "Reapply markups" icons
	$('.wp-list-table').on('click', '.js-mt2mba-reapply-markup', function(e) {
		e.preventDefault();
		const $link = $(this);
		const productId = $link.data('product-id');

		if (productId) {
			processReapply(productId, $link);
		} else {
			console.error('Product ID not found');
		}
	});

	function processBulkReapply(productIds) {
		const total = productIds.length;
		let processed = 0;

		// Add overlay to product list table
		const $table = $('.wp-list-table');
		const $overlay = $('<div class="mt2mba-processing-overlay"></div>');
		$table.css('position', 'relative').append($overlay);

		const $notice = $('<div class="notice notice-info mt2mba-bulk-progress"><p>' +
			'<span class="progress-text">' +
			mt2mbaListLocal.i18n.processing.replace('%1$s', '1').replace('%2$s', total) +
			'</span>' +
			'<span class="spinner is-active"></span>' +
			'</p></div>').insertAfter('.wp-header-end');

		function processNext() {
			if (processed >= total) {
				// Remove overlay
				$overlay.remove();
				$table.css('position', '');

				$notice.removeClass('notice-info').addClass('notice-success')
					.html('<p>' + mt2mbaListLocal.i18n[total === 1 ? 'processed' : 'processedPlural']
						.replace('%s', total) + '</p>');

				setTimeout(function() {
					$notice.fadeOut(400, function() {
						$(this).remove();
					});
				}, 5000);
				return;
			}

			const productId = productIds[processed];
			const $link = $('.js-mt2mba-reapply-markup[data-product-id="' + productId + '"]');

			processReapply(productId, $link, {
				success: function() {
					processed++;
					$notice.find('.progress-text').text(
						mt2mbaListLocal.i18n.processing
							.replace('%1$s', processed + 1)
							.replace('%2$s', total)
					);
					processNext();
				},
				error: function() {
					processed++;
					processNext();
				}
			});
		}

		processNext();
	}

	function processReapply(productId, $link, callbacks = {}) {
		// Don't process if already running
		if ($link && $link.hasClass('processing')) {
			return;
		}

		// Add processing state
		if ($link) {
			$link.addClass('processing').css('opacity', '0.5');
			const $icon = $link.find('.dashicons');
			$icon.addClass('dashicons-update-spin');
		}

		// Send Ajax request
		$.ajax({
			url: ajaxurl,
			type: 'POST',
			data: {
				action: 'handleMarkupReapplication',
				product_id: productId,
				security: mt2mbaListLocal.security
			},
			success: function(response) {
				if (response && response.success) {
					if ($link) {
						$link.css('opacity', '1');
						const $icon = $link.find('.dashicons');
						$icon.removeClass('dashicons-update-spin').addClass('dashicons-yes');

						// Get fresh row HTML
						$.ajax({
							url: ajaxurl,
							type: 'POST',
							data: {
								action: 'mt2mba_refresh_product_row',
								product_id: productId,
								security: mt2mbaListLocal.security
							},
							success: function(rowResponse) {
								if (rowResponse.success) {
									const $row = $link.closest('tr');
									const $priceCell = $row.find('.column-price');
									if ($priceCell.length && rowResponse.data.price) {
										$priceCell.html(rowResponse.data.price);
									}
								}
							},
							// The markup itself was already applied and saved before this
							// request went out, so the row has to be handed back whether or
							// not the fresh HTML arrives. When this lived in success() a
							// failed refresh left the icon stuck on the checkmark with
							// .processing still set, and that row could not be reapplied
							// again without a page reload.
							complete: function() {
								setTimeout(function() {
									$icon.removeClass('dashicons-yes').addClass('dashicons-update');
									$link.removeClass('processing');
								}, 2000);
							}
						});
					}
					if (callbacks.success) callbacks.success();
				} else {
					showReapplyFailure($link);
					if (callbacks.error) callbacks.error();
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {
				showReapplyFailure($link);
				if (callbacks.error) callbacks.error();
			}
		});
	}

	// Flag a failed reapply on the row icon, then hand the row back after a beat.
	// Both failure paths -- the server reporting failure and the request itself
	// erroring -- look identical to the user, so they share this.
	function showReapplyFailure($link) {
		if (!$link) return;

		$link.css('opacity', '1').css('color', 'red');
		const $icon = $link.find('.dashicons');
		$icon.removeClass('dashicons-update-spin').addClass('dashicons-warning');

		setTimeout(function() {
			$icon.removeClass('dashicons-warning').addClass('dashicons-update');
			$link.removeClass('processing').css('color', '');
		}, 3000);
	}
});