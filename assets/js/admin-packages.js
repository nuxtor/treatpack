/**
 * Treatment Packages Admin - Package Repeater UI
 *
 * @package TreatmentPackages
 */

(function($) {
    'use strict';

    /**
     * Package Admin Handler
     */
    var TPPackageAdmin = {
        /**
         * Current package index counter
         */
        currentIndex: 0,

        /**
         * Initialize
         */
        init: function() {
            this.currentIndex = $('#tp-packages-list tr').length;
            this.bindEvents();
            this.initSortable();
            this.calculateAllRows();
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Add single package
            $('#tp-add-package').on('click', this.addPackage.bind(this));

            // Add preset packages
            $('#tp-add-preset-packages').on('click', this.addPresetPackages.bind(this));

            // Delete package
            $(document).on('click', '.tp-delete-package', this.deletePackage.bind(this));

            // Price/sessions change - recalculate
            $(document).on('input', '.tp-input-sessions, .tp-input-price', this.onPriceChange.bind(this));

            // Deposit type change
            $(document).on('change', '.tp-input-deposit-type', this.onDepositTypeChange.bind(this));

            // Auto-generate name on sessions change
            $(document).on('change', '.tp-input-sessions', this.onSessionsChange.bind(this));
        },

        /**
         * Initialize sortable
         */
        initSortable: function() {
            $('#tp-packages-list').sortable({
                handle: '.tp-sort-handle',
                axis: 'y',
                opacity: 0.7,
                cursor: 'move',
                update: this.updateSortOrder.bind(this)
            });
        },

        /**
         * Add a new package row
         */
        addPackage: function(e) {
            if (e) {
                e.preventDefault();
            }

            var template = $('#tp-package-row-template').html();
            var html = template.replace(/\{\{INDEX\}\}/g, this.currentIndex);

            $('#tp-packages-list').append(html);

            // Apply default deposit settings
            var $newRow = $('#tp-packages-list tr:last');
            var defaultType = $('#tp-default-deposit-type').val();
            var defaultValue = $('#tp-default-deposit-value').val();

            if (defaultType && defaultType !== 'none') {
                $newRow.find('.tp-input-deposit-type').val(defaultType).trigger('change');
                $newRow.find('.tp-input-deposit-value').val(defaultValue);
            }

            this.currentIndex++;
            this.updateSortOrder();

            // Focus on sessions input
            $newRow.find('.tp-input-sessions').focus();

            return $newRow;
        },

        /**
         * Add preset packages (1, 6, 8, 10 sessions)
         */
        addPresetPackages: function(e) {
            e.preventDefault();

            var presets = [1, 6, 8, 10];
            var basePrice = parseFloat($('#tp_base_price').val()) || 0;

            // Check if any packages already exist
            if ($('#tp-packages-list tr').length > 0) {
                if (!confirm('This will add preset packages. Continue?')) {
                    return;
                }
            }

            presets.forEach(function(sessions, index) {
                var $row = this.addPackage();
                $row.find('.tp-input-sessions').val(sessions);

                // Calculate discounted price based on sessions
                var discount = this.getPresetDiscount(sessions);
                var totalPrice = 0;

                if (basePrice > 0) {
                    var discountedPerSession = basePrice * (1 - discount / 100);
                    totalPrice = (discountedPerSession * sessions).toFixed(2);
                }

                $row.find('.tp-input-price').val(totalPrice);

                // Set name
                var name = sessions === 1 ? 'Pay as you go' : 'Course of ' + sessions;
                if (discount > 0) {
                    name += ' - ' + discount + '% off';
                }
                $row.find('.tp-input-name').val(name);

                // Trigger calculation
                $row.find('.tp-input-price').trigger('input');
            }.bind(this));
        },

        /**
         * Get preset discount for session count
         */
        getPresetDiscount: function(sessions) {
            var discounts = {
                1: 50,
                6: 70,
                8: 75,
                10: 77
            };
            return discounts[sessions] || 0;
        },

        /**
         * Delete a package row
         */
        deletePackage: function(e) {
            e.preventDefault();

            if (!confirm(tpAdminPackages.i18n.confirmDelete)) {
                return;
            }

            $(e.currentTarget).closest('tr').fadeOut(200, function() {
                $(this).remove();
                TPPackageAdmin.updateSortOrder();
            });
        },

        /**
         * Handle price/sessions change
         */
        onPriceChange: function(e) {
            var $row = $(e.currentTarget).closest('tr');
            this.calculateRow($row);
        },

        /**
         * Calculate per-session price and discount for a row
         */
        calculateRow: function($row) {
            var sessions = parseInt($row.find('.tp-input-sessions').val()) || 1;
            var totalPrice = parseFloat($row.find('.tp-input-price').val()) || 0;
            var basePrice = parseFloat($('#tp_base_price').val()) || 0;

            // Calculate per-session price
            var perSession = sessions > 0 ? totalPrice / sessions : 0;
            var currencySymbol = $row.find('.tp-currency').text() || '£';

            $row.find('.tp-per-session-display').text(
                perSession > 0 ? currencySymbol + perSession.toFixed(2) : '—'
            );

            // Calculate discount percentage
            var discount = 0;
            if (basePrice > 0 && perSession > 0) {
                discount = ((basePrice - perSession) / basePrice) * 100;
            }

            $row.find('.tp-discount-display').text(
                discount > 0 ? Math.round(discount) + '%' : '—'
            );
        },

        /**
         * Calculate all rows
         */
        calculateAllRows: function() {
            var self = this;
            $('#tp-packages-list tr').each(function() {
                self.calculateRow($(this));
            });
        },

        /**
         * Handle deposit type change
         */
        onDepositTypeChange: function(e) {
            var $row = $(e.currentTarget).closest('tr');
            var depositType = $(e.currentTarget).val();
            var $wrapper = $row.find('.tp-deposit-value-wrapper');
            var $suffix = $row.find('.tp-deposit-suffix');
            var currencySymbol = $row.find('.tp-currency').text() || '£';

            if (depositType === 'none') {
                $wrapper.hide();
            } else {
                $wrapper.show();
                $suffix.text(depositType === 'percentage' ? '%' : currencySymbol);
            }
        },

        /**
         * Handle sessions change - auto-generate name
         */
        onSessionsChange: function(e) {
            var $row = $(e.currentTarget).closest('tr');
            var $nameInput = $row.find('.tp-input-name');
            var sessions = parseInt($(e.currentTarget).val()) || 1;

            // Only auto-generate if name is empty or matches a pattern
            var currentName = $nameInput.val();
            var isAutoGenerated = !currentName ||
                currentName === 'Pay as you go' ||
                currentName.match(/^Course of \d+/) ||
                currentName.match(/^Pay as you go - \d+% off$/) ||
                currentName.match(/^Course of \d+ - \d+% off$/);

            if (isAutoGenerated) {
                var newName = sessions === 1 ? 'Pay as you go' : 'Course of ' + sessions;
                // Don't set it - let the server handle auto-generation
                $nameInput.attr('placeholder', newName);
            }
        },

        /**
         * Update sort order after drag
         */
        updateSortOrder: function() {
            $('#tp-packages-list tr').each(function(index) {
                $(this).find('.tp-sort-order').val(index);
                $(this).attr('data-index', index);
            });
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        if ($('#tp-packages-list').length) {
            TPPackageAdmin.init();
        }

        // Recalculate when base price changes
        $('#tp_base_price').on('input', function() {
            TPPackageAdmin.calculateAllRows();
        });
    });

})(jQuery);
