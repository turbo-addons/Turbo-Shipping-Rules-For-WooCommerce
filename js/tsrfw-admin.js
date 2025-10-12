/**
 * Admin script for Turbo Shipping Rules for WooCommerce
 * Handles Select2 initialization on shipping settings page
 *
 * @since 1.0.0
 */

(function($) {
    'use strict';

    // Initialize Select2 for WooCommerce enhanced selects
    function initSelect2() {
        $('select.wc-enhanced-select').each(function() {
            if (!$(this).hasClass('select2-hidden-accessible')) {
                $(this).select2({
                    placeholder: tsrfwAdmin.placeholder || 'Select categories',
                    allowClear: true,
                    width: '100%'
                });
            }
        });
    }

    // Run immediately
    $(document).ready(function() {
        initSelect2();

        // Reinitialize after modal loads (WooCommerce behavior)
        $(document.body).on('wc_backbone_modal_loaded', initSelect2);
    });

})(jQuery);
