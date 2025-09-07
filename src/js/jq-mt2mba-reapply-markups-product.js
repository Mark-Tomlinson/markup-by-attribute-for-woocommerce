/**
 * jQuery script for handling markup recalculation and base price display in WooCommerce product variations.
 * This script adds a "Recalculate markup" button to the variations tab and handles
 * the AJAX interaction for recalculating markups across all variations.
 *
 * @requires jQuery
 * @requires woocommerce_admin_meta_boxes
 * @requires mt2mbaLocal (localized script data)
 */
jQuery(document).ready(function($) {
	// Helper function to get cookie value
	function getCookie(name) {
		const value = `; ${document.cookie}`;
		const parts = value.split(`; ${name}=`);
		if (parts.length === 2) return parts.pop().split(';').shift();
		return null;
	}

	// Find 'Pricing' group in bulk actions (second group)
	var $select = $('#variable_product_options select.variation_actions');
	var $pricingGroup = $select.find('optgroup').eq(1);

	if ($pricingGroup.length) {
		$pricingGroup.prepend(
			$('<option>', {
				value: 'reapply_markup',
				text: mt2mbaLocal.i18n.reapplyMarkupss
			})
		);
	}

	// Add listener for clicking the General tab
	$('.product_data_tabs .general_tab a').on('click', function() {
		// Only refresh if prices were changed
		if (getCookie('mt2mba_prices_changed')) {
			var product_id = $('#post_ID').val();

			// Refresh the [General] panel
			$.ajax({
				url: mt2mbaLocal.ajaxUrl,
				type: 'POST',
				data: {
					action: 'mt2mba_refresh_general_panel',
					product_id: product_id,
					security: mt2mbaLocal.security
				},
				success: function(response) {
					if (response.success) {
						// Target the specific options group
						$('.panel-wrap.product_data .options_group.show_if_variable').html(response.data.html);
						// Clear the cookie since we've refreshed
						document.cookie = 'mt2mba_prices_changed=; Path=/; Expires=Thu, 01 Jan 1970 00:00:01 GMT;';
					}
				},
				error: function(jqXHR, textStatus, errorThrown) {  // Add error handler
					console.log('Ajax error:', textStatus, errorThrown);
				}
			});
		}
	});

	// Handle the bulk variation action selection changes
	$('.wc-metaboxes-wrapper').on('change', '.variation_actions', function() {
		var $select = $(this);
		var action = $select.val();

		// Set cookie if this is a pricing action
		if (action.includes('price')) {
			document.cookie = 'mt2mba_prices_changed=true; Path=/;';
		}

		if (action === 'reapply_markup') {
			var product_id = $('#post_ID').val();

			// Get freshly formatted price before showing confirmation
			$.ajax({
				url: mt2mbaLocal.ajaxUrl,
				type: 'POST',
				data: {
					action: 'getFormattedBasePrice',
					product_id: product_id,
					security: mt2mbaLocal.security
				},
				success: function(response) {
					if (response.success) {
						if (confirm(mt2mbaLocal.i18n.confirmReapply.replace('%s', response.data.formatted_price))) {
							// Send Ajax request
							$.ajax({
								url: mt2mbaLocal.ajaxUrl,
								type: 'POST',
								data: {
									action: 'handleMarkupReapplication',
									product_id: product_id,
									security: mt2mbaLocal.security
								},
								success: function(response) {
									if (response.success) {
										var $wrapper = $('.woocommerce_variations.wc-metaboxes');

										// Get current page and items per page
										var page_no = $('.variations-pagenav .page-selector').val();
										var per_page = $('.woocommerce_variations .woocommerce_variation').length;

										// Reload variations panel
										$.ajax({
											url: mt2mbaLocal.ajaxUrl,
											data: {
												action: 'woocommerce_load_variations',
												product_id: product_id,
												page: page_no,
												per_page: per_page,
												security: mt2mbaLocal.variationsNonce
											},
											type: 'POST',
											success: function(html) {
												// Replace the variations content
												$wrapper.html(html);
												// Tell WooCommerce the variations panel is reloaded
												$wrapper.trigger('woocommerce_variations_loaded');
												// Tell WooCommerce to update all related panels
												$('body').trigger('woocommerce_variations_saved');
											}
										});
									}
								}
							});
						}
					}
				}
			});

			// Reset select
			$select.val('bulk_actions');
		}
	});
});