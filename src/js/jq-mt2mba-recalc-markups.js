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
    /**
     * Initializes the recalculate markup button if we're on the variations tab.
     * Only adds the button if it doesn't already exist.
     */
    function initializeRecalcMarkup() {
        // Check if we're on the variations tab and the button doesn't exist yet
        if ($('#variable_product_options').is(':visible') && $('.recalc-markup').length === 0) {
            // Add the recalculate button to the variations toolbar
            $('#variable_product_options .toolbar.toolbar-top').append(
                '<button type="button" class="button recalc-markup">' + mt2mbaLocal.buttonText + '</button>'
            );
        }
    }

    // Initialize on page load if variations tab is active
    if ($('.variations_tab').hasClass('active')) {
        initializeRecalcMarkup();
    }

    // Initialize when switching to variations tab
    $('.variations_tab > a').on('click', function() {
        setTimeout(initializeRecalcMarkup, 100);
    });

    /**
     * Handles recalculation of markup values for product variations.
     * This involves two sequential AJAX operations:
     * 1. Recalculate the markups (our custom handler)
     * 2. Reload the variations panel with new values (WooCommerce handler)
     * 
     * Both operations require their own security nonces, and the UI remains
     * blocked until both operations complete successfully.
     */
    $(document).on('click', '.recalc-markup', function(e) {
        e.preventDefault();
        
        var $wrapper = $('#variable_product_options');
        
        // Block UI during both operations
        $wrapper.block({
            message: null,
            overlayCSS: {
                background: '#fff',
                opacity: 0.6
            }
        });

        // First AJAX call: Recalculate markups
        $.ajax({
            url: mt2mbaLocal.ajaxUrl,
            data: {
                action: 'mt2mba_recalculate_markup',
                product_id: mt2mbaLocal.productId,
                security: mt2mbaLocal.security
            },
            type: 'POST'
        }).done(function(response) {
            if (response && response.success) {
                // Get current page number and number of items per page
                var page_no = $('#current-page-selector-1').val();
                var per_page = $('.woocommerce_variations .woocommerce_variation').length;

                // Second AJAX call: Reload variations panel with new values
                $.ajax({
                    url: mt2mbaLocal.ajaxUrl,
                    data: {
                        action: 'woocommerce_load_variations',
                        product_id: mt2mbaLocal.productId,
                        page: page_no,
                        per_page: per_page,
                        security: mt2mbaLocal.variationsNonce
                    },
                    type: 'POST',
                    success: function(html) {
                        // Update the variations panel and trigger WooCommerce's update event
                        $('.woocommerce_variations').html(html);
                        $(document.body).trigger('woocommerce_variations_loaded');
                    },
                    error: function(jqXHR, textStatus, errorThrown) {
                        console.error('Failed to load variations:', textStatus, errorThrown);
                        alert('Failed to load updated variations. Please refresh the page.');
                    },
                    complete: function() {
                        $wrapper.unblock();
                    }
                });
            } else {
                // First AJAX call failed with error response
                console.error('Recalculation error:', response);
                alert(mt2mbaLocal.i18n.errorRecalculating);
                $wrapper.unblock();
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            // First AJAX call failed to complete
            console.error('Failed to recalculate markups:', textStatus, errorThrown);
            alert(mt2mbaLocal.i18n.failedRecalculating);
            $wrapper.unblock();
        });
    });
});