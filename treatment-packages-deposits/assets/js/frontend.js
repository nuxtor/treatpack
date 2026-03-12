/**
 * Treatment Packages & Deposits - Frontend JavaScript
 *
 * @package TreatmentPackages
 */

(function($) {
    'use strict';

    /**
     * Treatment Packages Frontend Handler
     */
    var TPDeposits = {
        /**
         * Initialize
         */
        init: function() {
            this.bindEvents();
            this.filterInitialCategory();
        },

        /**
         * Filter to show only the initially active category
         */
        filterInitialCategory: function() {
            var $wrapper = $('.tp-packages-wrapper');
            if (!$wrapper.length) return;

            var $activeItem = $wrapper.find('.tp-category-item.active');
            if ($activeItem.length) {
                var category = $activeItem.find('.tp-category-link').data('category');
                if (category) {
                    this.filterByCategory($wrapper, category);
                }
            }
        },

        /**
         * Filter cards by category
         */
        filterByCategory: function($wrapper, category) {
            var $grid = $wrapper.find('.tp-packages-grid');
            var $cards = $grid.find('.tp-package-card');

            if (category === 'all') {
                $cards.show();
            } else {
                $cards.each(function() {
                    var $card = $(this);
                    var cardCategories = ($card.data('category') || '').toString().split(' ');

                    if (cardCategories.indexOf(category) !== -1) {
                        $card.show();
                    } else {
                        $card.hide();
                    }
                });
            }

            // Show message if no treatments visible
            var visibleCards = $cards.filter(':visible').length;
            $wrapper.find('.tp-no-treatments').remove();

            if (visibleCards === 0) {
                $grid.append('<p class="tp-no-treatments">' + (typeof tpDeposits !== 'undefined' && tpDeposits.i18n && tpDeposits.i18n.noTreatments ? tpDeposits.i18n.noTreatments : 'No treatments found in this category.') + '</p>');
            }
        },

        /**
         * Bind event handlers
         */
        bindEvents: function() {
            // Dropdown toggle
            $(document).on('click', '.tp-dropdown-toggle', this.toggleDropdown);

            // Close dropdown when clicking outside
            $(document).on('click', this.closeDropdownOnOutsideClick);

            // Order button click
            $(document).on('click', '.tp-order-btn', this.handleOrderClick);

            // Category filter
            $(document).on('click', '.tp-category-link', this.handleCategoryClick);
        },

        /**
         * Toggle session dropdown
         */
        toggleDropdown: function(e) {
            e.preventDefault();
            e.stopPropagation();

            var $dropdown = $(this).closest('.tp-session-dropdown');
            var isOpen = $dropdown.hasClass('open');

            // Close all other dropdowns
            $('.tp-session-dropdown.open').removeClass('open');

            // Toggle current dropdown
            if (!isOpen) {
                $dropdown.addClass('open');
            }
        },

        /**
         * Close dropdown when clicking outside
         */
        closeDropdownOnOutsideClick: function(e) {
            if (!$(e.target).closest('.tp-session-dropdown').length) {
                $('.tp-session-dropdown.open').removeClass('open');
            }
        },

        /**
         * Handle order button click
         */
        handleOrderClick: function(e) {
            e.preventDefault();

            var $btn = $(this);
            var $option = $btn.closest('.tp-session-option');
            var productId = $option.data('product-id');
            var packageId = $option.data('package-id');

            if (!productId) {
                console.error('No product ID found');
                return;
            }

            // Disable button and show loading state
            $btn.addClass('loading').text(tpDeposits.i18n.addingToCart);

            // Add to cart via AJAX
            $.ajax({
                url: tpDeposits.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tp_add_to_cart',
                    product_id: productId,
                    package_id: packageId,
                    nonce: tpDeposits.nonce
                },
                success: function(response) {
                    if (response.success) {
                        // Redirect to cart page
                        window.location.href = tpDeposits.cartUrl;
                    } else {
                        alert(response.data.message || tpDeposits.i18n.error);
                        $btn.removeClass('loading').text('+ ORDER');
                    }
                },
                error: function() {
                    alert(tpDeposits.i18n.error);
                    $btn.removeClass('loading').text('+ ORDER');
                }
            });
        },

        /**
         * Handle category filter click
         */
        handleCategoryClick: function(e) {
            e.preventDefault();

            var $link = $(this);
            var category = $link.data('category');
            var $wrapper = $link.closest('.tp-packages-wrapper');

            // Update active state
            $wrapper.find('.tp-category-item').removeClass('active');
            $link.closest('.tp-category-item').addClass('active');

            // Close any open dropdowns
            $('.tp-session-dropdown.open').removeClass('open');

            // Filter using the shared method
            TPDeposits.filterByCategory($wrapper, category);
        },

        /**
         * Handle AJAX category filter (alternative method)
         */
        handleCategoryClickAjax: function(e) {
            e.preventDefault();

            var $link = $(this);
            var category = $link.data('category');
            var $wrapper = $link.closest('.tp-packages-wrapper');
            var $grid = $wrapper.find('.tp-packages-grid');

            // Update active state
            $wrapper.find('.tp-category-item').removeClass('active');
            $link.closest('.tp-category-item').addClass('active');

            // Show loading
            $grid.html('<div class="tp-loading"><div class="tp-loading-spinner"></div></div>');

            // Fetch via AJAX
            $.ajax({
                url: tpDeposits.ajaxUrl,
                type: 'POST',
                data: {
                    action: 'tp_filter_treatments',
                    category: category,
                    nonce: tpDeposits.nonce
                },
                success: function(response) {
                    if (response.success) {
                        $grid.html(response.data.html);
                    } else {
                        $grid.html('<p class="tp-no-treatments">' + (response.data.message || 'Error loading treatments.') + '</p>');
                    }
                },
                error: function() {
                    $grid.html('<p class="tp-no-treatments">Error loading treatments. Please try again.</p>');
                }
            });
        }
    };

    /**
     * Initialize on document ready
     */
    $(document).ready(function() {
        TPDeposits.init();
    });

    /**
     * Also run on window load to ensure all elements are ready
     */
    $(window).on('load', function() {
        TPDeposits.filterInitialCategory();
    });

})(jQuery);
