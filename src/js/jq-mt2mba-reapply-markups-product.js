/**
 * jQuery script for handling markup recalculation in WooCommerce product variations.
 * This script adds a "Recalculate markup" button to the variations tab and handles
 * the AJAX interaction for recalculating markups across all variations.
 *
 * @requires jQuery
 * @requires woocommerce_admin_meta_boxes
 * @requires mt2mbaLocal (localized script data)
 */
jQuery(document).ready(function($) {
	// Find 'Pricing' group in bulk actions (second group)
	var $select = $('#variable_product_options select.variation_actions');
	var $pricingGroup = $select.find('optgroup').eq(1);

	// Add our option to the pricing group
	if ($pricingGroup.length) {
		$pricingGroup.prepend(  // Prepend so that it is first
			$('<option>', {
				value:	'reapply_markup',
				text:	mt2mbaLocal.i18n.reapplyMarkups || 'Reapply markups to prices'
			})
		);
	}

	// Handle the bulk variation action selection changes
	$('.wc-metaboxes-wrapper').on('change', '.variation_actions', function() {
		var $select = $(this);	// Grab variation action drop-down
		if ($select.val() === 'reapply_markup') {	// If selection is reapply_markup
			
			// Get product ID from the post form
			var product_id = $('#post_ID').val();

			// Send reapply request
			$.ajax({
				url: mt2mbaLocal.ajaxUrl,
				data: {
					action: 'mt2mba_reapply_markup',	// Identified as wp_ajax_mt2mba_reapply_markup onserver side
					product_id: product_id,
					security: mt2mbaLocal.security
				},
				type: 'POST',
			}).done(function(response) {
				if (response && response.success) {
					// Get current page and items per page
					var page_no = $('.variations-pagenav .page-selector').val();
					var per_page = $('.woocommerce_variations .woocommerce_variation').length;
					
					// Reload variations panel with new values
					$.ajax({
						url: mt2mbaLocal.ajaxUrl,
						data: {
							action: 'woocommerce_load_variations',
							product_id: mt2mbaLocal.productId,
							page: page_no,
							per_page: per_page,
							security: mt2mbaLocal.variationsNonce
						},
						type: 'POST'
					});
				} else {
					alert(mt2mbaLocal.i18n.failedRecalculating);
					$select.val('bulk_actions');
				}
			}).fail(function(jqXHR, textStatus, errorThrown) {
				alert(mt2mbaLocal.i18n.failedRecalculating);
				$select.val('bulk_actions');
			});
		}
	});
});