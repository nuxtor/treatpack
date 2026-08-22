/**
 * TreatPack Customer Packages Admin Scripts
 */

(function($) {
    'use strict';

    var TreatPackCustomer = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Use session
            $(document).on('click', '.treatpack-use-session', this.useSession);

            // Record payment
            $(document).on('click', '.treatpack-record-payment', this.recordPayment);

            // Change status
            $(document).on('click', '.treatpack-change-status', this.toggleStatusDropdown);
            $(document).on('click', '.treatpack-save-status', this.saveStatus);
        },

        useSession: function(e) {
            e.preventDefault();

            if (!confirm(treatpackCustomer.i18n.confirm_use_session)) {
                return;
            }

            var $row = $(this).closest('tr');
            var packageId = $row.data('package-id');
            var $button = $(this);

            $button.prop('disabled', true);

            $.post(treatpackCustomer.ajax_url, {
                action: 'treatpack_use_session',
                nonce: treatpackCustomer.nonce,
                package_id: packageId
            }, function(response) {
                if (response.success) {
                    // Update sessions display
                    var $sessions = $row.find('.sessions-display');
                    $sessions.find('strong').text(response.data.sessions_used);
                    $row.find('td:nth-child(4) small').text(response.data.sessions_remaining + ' remaining');

                    // Update status if changed
                    if (response.data.status !== 'active') {
                        location.reload();
                    }

                    // Hide button if no more sessions
                    if (response.data.sessions_remaining <= 0) {
                        $button.hide();
                    }
                } else {
                    alert(response.data.message);
                }
            }).always(function() {
                $button.prop('disabled', false);
            });
        },

        recordPayment: function(e) {
            e.preventDefault();

            var amount = prompt(treatpackCustomer.i18n.enter_amount);

            if (!amount || isNaN(parseFloat(amount))) {
                return;
            }

            var $row = $(this).closest('tr');
            var packageId = $row.data('package-id');
            var $button = $(this);

            $button.prop('disabled', true);

            $.post(treatpackCustomer.ajax_url, {
                action: 'treatpack_record_payment',
                nonce: treatpackCustomer.nonce,
                package_id: packageId,
                amount: parseFloat(amount)
            }, function(response) {
                if (response.success) {
                    // Update balance display
                    var $balance = $row.find('td:nth-child(5)');

                    if (response.data.balance_remaining <= 0) {
                        $balance.html('<span class="balance-paid">' + response.data.formatted_balance + '</span>');
                        $button.hide();
                    } else {
                        $balance.find('.balance-due').html(response.data.formatted_balance);
                    }
                } else {
                    alert(response.data.message);
                }
            }).always(function() {
                $button.prop('disabled', false);
            });
        },

        toggleStatusDropdown: function(e) {
            e.preventDefault();

            var $row = $(this).closest('tr');
            var $dropdown = $row.find('.status-dropdown');

            $('.status-dropdown').not($dropdown).hide();
            $dropdown.toggle();
        },

        saveStatus: function(e) {
            e.preventDefault();

            var $row = $(this).closest('tr');
            var packageId = $row.data('package-id');
            var status = $row.find('.status-select').val();

            $.post(treatpackCustomer.ajax_url, {
                action: 'treatpack_update_status',
                nonce: treatpackCustomer.nonce,
                package_id: packageId,
                status: status
            }, function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.data.message);
                }
            });
        }
    };

    $(document).ready(function() {
        TreatPackCustomer.init();
    });

})(jQuery);
