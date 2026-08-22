/**
 * TreatPack Package Admin Scripts
 */

(function($) {
    'use strict';

    var TreatPackPackages = {
        init: function() {
            this.$wrap = $('.treatpack-packages-wrap');
            this.$list = $('#treatpack-packages-list');
            this.treatmentId = this.$wrap.data('treatment-id');

            if (!this.$wrap.length) {
                return;
            }

            this.bindEvents();
            this.initSortable();
            this.initDepositFields();
        },

        bindEvents: function() {
            var self = this;

            // Save package (new)
            this.$wrap.on('click', '.treatpack-add-package .treatpack-save-package', function(e) {
                e.preventDefault();
                self.savePackage($(this).closest('.treatpack-package-form'), false);
            });

            // Save package (edit)
            this.$wrap.on('click', '.treatpack-package-row .treatpack-save-package', function(e) {
                e.preventDefault();
                self.savePackage($(this).closest('.treatpack-package-form'), true);
            });

            // Edit package
            this.$wrap.on('click', '.treatpack-edit-package', function(e) {
                e.preventDefault();
                var $row = $(this).closest('.treatpack-package-row');
                $row.addClass('editing');
            });

            // Cancel edit
            this.$wrap.on('click', '.treatpack-cancel-edit', function(e) {
                e.preventDefault();
                var $row = $(this).closest('.treatpack-package-row');
                $row.removeClass('editing');
            });

            // Delete package
            this.$wrap.on('click', '.treatpack-delete-package', function(e) {
                e.preventDefault();
                if (confirm(treatpackPackages.i18n.confirm_delete)) {
                    self.deletePackage($(this).closest('.treatpack-package-row'));
                }
            });

            // Deposit type change
            this.$wrap.on('change', 'select[name="deposit_type"]', function() {
                self.toggleDepositField($(this));
            });
        },

        initSortable: function() {
            var self = this;

            this.$list.sortable({
                handle: '.treatpack-package-handle',
                placeholder: 'treatpack-package-row ui-sortable-placeholder',
                update: function() {
                    self.updateOrder();
                }
            });
        },

        initDepositFields: function() {
            var self = this;
            this.$wrap.find('select[name="deposit_type"]').each(function() {
                self.toggleDepositField($(this));
            });
        },

        toggleDepositField: function($select) {
            var type = $select.val();
            var $field = $select.closest('.treatpack-form-row').find('.treatpack-deposit-amount-field');

            if (type === 'none' || type === 'pay_at_location') {
                $field.addClass('hidden');
            } else {
                $field.removeClass('hidden');
            }
        },

        savePackage: function($form, isEdit) {
            var self = this;
            var $button = $form.find('.treatpack-save-package');
            var originalText = $button.text();

            var data = {
                action: 'treatpack_save_package',
                nonce: treatpackPackages.nonce,
                treatment_id: this.treatmentId,
                package_id: $form.find('input[name="package_id"]').val(),
                name: $form.find('input[name="name"]').val(),
                sessions: $form.find('input[name="sessions"]').val(),
                price: $form.find('input[name="price"]').val(),
                sale_price: $form.find('input[name="sale_price"]').val(),
                deposit_type: $form.find('select[name="deposit_type"]').val(),
                deposit_amount: $form.find('input[name="deposit_amount"]').val(),
                description: $form.find('textarea[name="description"]').val(),
                is_active: $form.find('input[name="is_active"]').is(':checked') ? '1' : '0'
            };

            $button.addClass('loading').text(treatpackPackages.i18n.saving);

            $.post(treatpackPackages.ajax_url, data, function(response) {
                if (response.success) {
                    if (isEdit) {
                        var $row = $form.closest('.treatpack-package-row');
                        $row.replaceWith(response.data.html);
                    } else {
                        self.$list.find('.treatpack-no-packages').remove();
                        self.$list.append(response.data.html);
                        self.resetForm($form);
                    }

                    $button.text(treatpackPackages.i18n.saved);
                    setTimeout(function() {
                        $button.removeClass('loading').text(originalText);
                    }, 1500);
                } else {
                    alert(response.data.message || treatpackPackages.i18n.error);
                    $button.removeClass('loading').text(originalText);
                }
            }).fail(function() {
                alert(treatpackPackages.i18n.error);
                $button.removeClass('loading').text(originalText);
            });
        },

        deletePackage: function($row) {
            var packageId = $row.data('package-id');

            $.post(treatpackPackages.ajax_url, {
                action: 'treatpack_delete_package',
                nonce: treatpackPackages.nonce,
                package_id: packageId
            }, function(response) {
                if (response.success) {
                    $row.slideUp(200, function() {
                        $(this).remove();
                    });
                } else {
                    alert(response.data.message || treatpackPackages.i18n.error);
                }
            });
        },

        updateOrder: function() {
            var order = [];

            this.$list.find('.treatpack-package-row').each(function() {
                order.push($(this).data('package-id'));
            });

            $.post(treatpackPackages.ajax_url, {
                action: 'treatpack_update_package_order',
                nonce: treatpackPackages.nonce,
                order: order
            });
        },

        resetForm: function($form) {
            $form.find('input[name="name"]').val('');
            $form.find('input[name="sessions"]').val('1');
            $form.find('input[name="price"]').val('');
            $form.find('input[name="sale_price"]').val('');
            $form.find('select[name="deposit_type"]').val('none').trigger('change');
            $form.find('input[name="deposit_amount"]').val('');
            $form.find('textarea[name="description"]').val('');
            $form.find('input[name="is_active"]').prop('checked', true);
        }
    };

    $(document).ready(function() {
        TreatPackPackages.init();
    });

})(jQuery);
