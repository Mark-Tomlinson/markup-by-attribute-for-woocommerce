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

    if ($pricingGroup.length) {
        $pricingGroup.prepend(
            $('<option>', {
                value: 'reapply_markup',
                text: mt2mbaLocal.i18n.reapplyMarkups
            })
        );
    }

    // Handle the bulk variation action selection changes
    $('.wc-metaboxes-wrapper').on('change', '.variation_actions', function() {
        var $select = $(this);
        if ($select.val() === 'reapply_markup') {
            var product_id = $('#post_ID').val();
            var base_price = mt2mbaLocal.basePrice;
            if (confirm(mt2mbaLocal.i18n.confirmReapply.replace('%s', base_price))) {
                // Send Ajax request
                $.ajax({
                    url: mt2mbaLocal.ajaxUrl,
                    type: 'POST',
                    data: {
                        action: 'mt2mba_reapply_markup',
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
                                    $wrapper.trigger('woocommerce_variations_loaded');
                                }
                            });
                        }
                    }
                });
            }
            // Reset select
            $select.val('bulk_actions');
        }
    });
});