/**
 * TreatPack Admin Scripts
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        // Initialize admin functionality
        TreatPackAdmin.init();
    });

    var TreatPackAdmin = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Deposit type change handler
            $('#treatpack_default_deposit_type').on('change', this.handleDepositTypeChange);
        },

        handleDepositTypeChange: function() {
            var type = $(this).val();
            var $wrap = $('#treatpack_deposit_amount_wrap');
            var $hint = $('#treatpack_deposit_hint');

            if (type === 'none' || type === 'pay_at_location') {
                $wrap.slideUp(200);
            } else {
                $wrap.slideDown(200);
                if (type === 'percentage') {
                    $hint.text(treatpack.i18n?.percentage_hint || 'Enter percentage (e.g., 20 for 20%)');
                } else {
                    $hint.text(treatpack.i18n?.fixed_hint || 'Enter fixed amount');
                }
            }
        }
    };

    window.TreatPackAdmin = TreatPackAdmin;

})(jQuery);
