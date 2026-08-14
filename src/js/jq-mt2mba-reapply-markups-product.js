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
	/**
	 * Add our action to the variations [Bulk actions] menu.
	 *
	 * Must be re-runnable: [Save attributes] does
	 * $('#variable_product_options').load(page + ' #variable_product_options_inner')
	 * (WooCommerce meta-boxes-product.js), which replaces the whole panel — select
	 * included — with server HTML that knows nothing about this option. Injecting
	 * only on document.ready meant the action vanished until the next full page
	 * load. Same behavior all the way back to WC 5.0.0.
	 */
	function addReapplyMarkupOption() {
		var $select = $('#variable_product_options select.variation_actions');

		// The reload events can fire more than once for one rebuild
		if ($select.find('option[value="reapply_markup"]').length) return;

		// Anchor on the action WooCommerce files under 'Pricing' rather than on the
		// group's position: this menu has gained groups more than once (WC 11 added
		// 'Cost of goods'), and the old optgroup.eq(1) was right only by luck.
		var $pricingGroup = $select.find('option[value="variable_regular_price"]').parent('optgroup');

		if ($pricingGroup.length) {
			$pricingGroup.prepend(
				$('<option>', {
					value: 'reapply_markup',
					text: mt2mbaLocal.i18n.reapplyMarkupss
				})
			);
		}
	}

	addReapplyMarkupOption();

	// 'reload' fires on #variable_product_options right after the panel is replaced;
	// 'woocommerce_variations_loaded' fires on #woocommerce-product-data once the rows
	// finish loading. Both bubble to body. Listening for both covers the case where a
	// product has no variations to load and the later event never arrives.
	$(document.body).on('woocommerce_variations_loaded woocommerce_variations_saved', addReapplyMarkupOption);
	$(document.body).on('reload', '#variable_product_options', addReapplyMarkupOption);

	// Add listener for clicking the General tab
	$('.product_data_tabs .general_tab a').on('click', function() {
		// Only refresh if prices were changed
		if (sessionStorage.getItem('mt2mba_prices_changed')) {

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
						// Update base price fields directly with returned values
						$('#base_regular_price').val(response.data.base_regular_price);
						$('#base_sale_price').val(response.data.base_sale_price);
						// Clear the prices-changed flag since we've refreshed
						sessionStorage.removeItem('mt2mba_prices_changed');
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

		// Set a prices-changed flag if this is a pricing action
		if (action.includes('price')) {
			sessionStorage.setItem('mt2mba_prices_changed', 'true');

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