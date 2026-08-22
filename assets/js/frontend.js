/**
 * TreatPack Frontend Scripts
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        TreatPack.init();
    });

    var TreatPack = {
        init: function() {
            this.bindEvents();
        },

        bindEvents: function() {
            // Package selection change
            $(document).on('change', '.treatpack-package-select', this.handlePackageChange);

            // Add to cart
            $(document).on('click', '.treatpack-add-to-cart', this.handleAddToCart);

            // Category filter
            $(document).on('click', '.treatpack-category-filter', this.handleCategoryFilter);
        },

        handlePackageChange: function() {
            var $select = $(this);
            var $card = $select.closest('.treatpack-treatment-card');
            var $button = $card.find('.treatpack-add-to-cart');
            var $priceDisplay = $card.find('.treatpack-selected-price');

            var selectedOption = $select.find('option:selected');
            var price = selectedOption.data('price');
            var sessions = selectedOption.data('sessions');

            if (price) {
                $priceDisplay.html(price + ' <small>(' + sessions + ' sessions)</small>');
                $button.prop('disabled', false);
            } else {
                $priceDisplay.html('');
                $button.prop('disabled', true);
            }
        },

        handleAddToCart: function(e) {
            e.preventDefault();

            var $button = $(this);
            var $card = $button.closest('.treatpack-treatment-card');
            var $select = $card.find('.treatpack-package-select');
            var packageId = $select.val();

            if (!packageId) {
                return;
            }

            $button.prop('disabled', true).text('Adding...');

            $.ajax({
                url: treatpack.ajax_url,
                type: 'POST',
                data: {
                    action: 'treatpack_add_to_cart',
                    package_id: packageId,
                    nonce: treatpack.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $button.text('Added!');
                        setTimeout(function() {
                            $button.prop('disabled', false).text('Add to Cart');
                        }, 2000);

                        // Update cart count if function exists
                        if (typeof wc_cart_fragments_params !== 'undefined') {
                            $(document.body).trigger('wc_fragment_refresh');
                        }
                    } else {
                        alert(response.data.message || 'Error adding to cart');
                        $button.prop('disabled', false).text('Add to Cart');
                    }
                },
                error: function() {
                    alert('Error adding to cart. Please try again.');
                    $button.prop('disabled', false).text('Add to Cart');
                }
            });
        },

        handleCategoryFilter: function(e) {
            e.preventDefault();

            var $link = $(this);
            var categoryId = $link.data('category');

            $('.treatpack-category-filter').removeClass('active');
            $link.addClass('active');

            if (categoryId === 'all') {
                $('.treatpack-treatment-card').show();
            } else {
                $('.treatpack-treatment-card').hide();
                $('.treatpack-treatment-card[data-categories*="' + categoryId + '"]').show();
            }
        }
    };

    window.TreatPack = TreatPack;

})(jQuery);
