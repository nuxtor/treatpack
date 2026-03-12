/**
 * Treatment Packages Admin - Customer Packages Screen
 *
 * @package TreatmentPackages
 */

(function($) {
    'use strict';

    /**
     * Customer Packages Admin Handler
     */
    var TPCustomerAdmin = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Use session button
            $(document).on('click', '.tp-use-session-btn', this.handleUseSession);

            // Record payment button
            $(document).on('click', '.tp-record-payment-btn', this.handleRecordPayment);

            // Cancel package button
            $(document).on('click', '.tp-cancel-package-btn', this.handleCancelPackage);
        },

        /**
         * Handle use session click
         */
        handleUseSession: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var packageId = $btn.data('id');

            if (!confirm(tpAdminCustomer.i18n.confirmUseSession)) {
                return;
            }

            $btn.addClass('tp-loading').prop('disabled', true);

            $.ajax({
                url: tpAdminCustomer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tp_use_session',
                    package_id: packageId,
                    nonce: tpAdminCustomer.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        TPCustomerAdmin.updateSessionsDisplay(packageId, response.data);

                        // Show success message
                        TPCustomerAdmin.showNotice('success', response.data.message);

                        // Reload if status changed to completed
                        if (response.data.status === 'completed') {
                            location.reload();
                        }
                    } else {
                        TPCustomerAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    TPCustomerAdmin.showNotice('error', tpAdminCustomer.i18n.error);
                },
                complete: function() {
                    $btn.removeClass('tp-loading').prop('disabled', false);
                }
            });
        },

        /**
         * Handle record payment click
         */
        handleRecordPayment: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var packageId = $btn.data('id');
            var $input = $('#tp-payment-amount');
            var amount = parseFloat($input.val());

            if (!amount || amount <= 0) {
                alert(tpAdminCustomer.i18n.enterPaymentAmount);
                $input.focus();
                return;
            }

            $btn.addClass('tp-loading').prop('disabled', true);

            $.ajax({
                url: tpAdminCustomer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tp_record_payment',
                    package_id: packageId,
                    amount: amount,
                    nonce: tpAdminCustomer.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Update UI
                        TPCustomerAdmin.updateBalanceDisplay(packageId, response.data);

                        // Clear input
                        $input.val('');

                        // Show success message
                        TPCustomerAdmin.showNotice('success', response.data.message);

                        // Reload if fully paid
                        if (response.data.remaining_balance <= 0) {
                            location.reload();
                        }
                    } else {
                        TPCustomerAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    TPCustomerAdmin.showNotice('error', tpAdminCustomer.i18n.error);
                },
                complete: function() {
                    $btn.removeClass('tp-loading').prop('disabled', false);
                }
            });
        },

        /**
         * Handle cancel package click
         */
        handleCancelPackage: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var packageId = $btn.data('id');

            var reason = prompt(tpAdminCustomer.i18n.confirmCancel + '\n\nEnter reason (optional):');

            if (reason === null) {
                return; // User cancelled
            }

            $btn.addClass('tp-loading').prop('disabled', true);

            $.ajax({
                url: tpAdminCustomer.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tp_update_package_status',
                    package_id: packageId,
                    status: 'cancelled',
                    reason: reason,
                    nonce: tpAdminCustomer.nonce
                },
                success: function(response) {
                    if (response.success) {
                        TPCustomerAdmin.showNotice('success', response.data.message);
                        location.reload();
                    } else {
                        TPCustomerAdmin.showNotice('error', response.data.message);
                    }
                },
                error: function() {
                    TPCustomerAdmin.showNotice('error', tpAdminCustomer.i18n.error);
                },
                complete: function() {
                    $btn.removeClass('tp-loading').prop('disabled', false);
                }
            });
        },

        /**
         * Update sessions display after using a session
         */
        updateSessionsDisplay: function(packageId, data) {
            var $row = $('tr[data-package-id="' + packageId + '"]');

            if ($row.length) {
                // Update list view
                $row.find('.tp-sessions-display .remaining').text(data.sessions_remaining);

                // Hide button if no sessions left
                if (data.sessions_remaining <= 0) {
                    $row.find('.tp-use-session-btn').hide();
                }

                // Highlight row
                $row.addClass('tp-highlight');
            }

            // Update single view
            var $singleRemaining = $('.tp-sessions-remaining .number');
            if ($singleRemaining.length) {
                $singleRemaining.text(data.sessions_remaining);

                // Hide button if no sessions left
                if (data.sessions_remaining <= 0) {
                    $('.tp-actions-box .tp-use-session-btn').hide();
                }
            }
        },

        /**
         * Update balance display after recording payment
         */
        updateBalanceDisplay: function(packageId, data) {
            var $row = $('tr[data-package-id="' + packageId + '"]');

            if ($row.length) {
                var $balanceCell = $row.find('.column-balance');

                if (data.remaining_balance <= 0) {
                    $balanceCell.html('<span class="tp-paid-full">Paid in full</span>');
                } else {
                    $balanceCell.find('.tp-balance-due').html(data.formatted_balance);
                }

                // Highlight row
                $row.addClass('tp-highlight');
            }

            // Update single view
            var $balanceRow = $('.tp-balance-row');
            if ($balanceRow.length) {
                if (data.remaining_balance <= 0) {
                    $balanceRow.removeClass('has-balance')
                        .find('td').html('<span class="tp-paid-full">Paid in full</span>');

                    // Hide payment form
                    $('.tp-record-payment').hide();
                } else {
                    $balanceRow.find('td strong').html(data.formatted_balance);

                    // Update max on input
                    $('#tp-payment-amount').attr('max', data.remaining_balance);
                }
            }
        },

        /**
         * Show admin notice
         */
        showNotice: function(type, message) {
            var noticeClass = type === 'success' ? 'notice-success' : 'notice-error';

            var $notice = $('<div class="notice ' + noticeClass + ' is-dismissible"><p>' + message + '</p></div>');

            // Remove existing notices
            $('.wrap > .notice').remove();

            // Add new notice
            $('.wrap h1').after($notice);

            // Auto dismiss after 5 seconds
            setTimeout(function() {
                $notice.fadeOut(function() {
                    $(this).remove();
                });
            }, 5000);

            // Scroll to top
            $('html, body').animate({ scrollTop: 0 }, 300);
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        TPCustomerAdmin.init();
    });

})(jQuery);
