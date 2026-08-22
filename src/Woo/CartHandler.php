<?php

namespace TreatmentPackages\Woo;

use TreatmentPackages\Packages\PackageRepository;

defined('ABSPATH') || exit;

/**
 * WooCommerce Cart Handler
 *
 * Handles cart price adjustments for deposits and package display
 */
class CartHandler {

    /**
     * Constructor
     */
    public function __construct() {
        // AJAX add to cart
        add_action('wp_ajax_treatpack_add_to_cart', [$this, 'ajax_add_to_cart']);
        add_action('wp_ajax_nopriv_treatpack_add_to_cart', [$this, 'ajax_add_to_cart']);

        // Cart item data
        add_filter('woocommerce_add_cart_item_data', [$this, 'add_cart_item_data'], 10, 3);
        add_filter('woocommerce_get_cart_item_from_session', [$this, 'get_cart_item_from_session'], 10, 2);

        // Cart price adjustments for deposits
        add_action('woocommerce_before_calculate_totals', [$this, 'adjust_cart_prices'], 20);

        // Cart item display
        add_filter('woocommerce_get_item_data', [$this, 'display_cart_item_data'], 10, 2);
        add_filter('woocommerce_cart_item_name', [$this, 'modify_cart_item_name'], 10, 3);
    }

    /**
     * AJAX: Add package to cart
     */
    public function ajax_add_to_cart(): void {
        check_ajax_referer('treatpack_frontend', 'nonce');

        $package_id = absint($_POST['package_id'] ?? 0);

        if (!$package_id) {
            wp_send_json_error(['message' => __('Invalid package', 'treatpack')]);
        }

        $package = PackageRepository::find($package_id);

        if (!$package || !$package->is_active()) {
            wp_send_json_error(['message' => __('Package not available', 'treatpack')]);
        }

        $product_id = $package->get_product_id();

        if (!$product_id) {
            wp_send_json_error(['message' => __('Product not found', 'treatpack')]);
        }

        // Add to cart with custom data
        $cart_item_key = WC()->cart->add_to_cart($product_id, 1, 0, [], [
            'treatpack_package_id' => $package_id,
            'treatpack_treatment_id' => $package->get_treatment_id(),
        ]);

        if ($cart_item_key) {
            wp_send_json_success([
                'message' => __('Added to cart', 'treatpack'),
                'cart_url' => wc_get_cart_url(),
            ]);
        } else {
            wp_send_json_error(['message' => __('Could not add to cart', 'treatpack')]);
        }
    }

    /**
     * Add custom data to cart item
     *
     * @param array $cart_item_data Cart item data
     * @param int   $product_id     Product ID
     * @param int   $variation_id   Variation ID
     * @return array
     */
    public function add_cart_item_data(array $cart_item_data, int $product_id, int $variation_id): array {
        $package_id = ProductSync::get_package_id_from_product($product_id);

        if ($package_id) {
            $package = PackageRepository::find($package_id);

            if ($package) {
                $cart_item_data['treatpack_package_id'] = $package_id;
                $cart_item_data['treatpack_treatment_id'] = $package->get_treatment_id();
                $cart_item_data['treatpack_sessions'] = $package->get_sessions();
                $cart_item_data['treatpack_deposit_type'] = $package->get_deposit_type();
                $cart_item_data['treatpack_deposit_amount'] = $package->calculate_deposit();
                $cart_item_data['treatpack_balance_due'] = $package->calculate_balance();
                $cart_item_data['treatpack_total_price'] = $package->get_active_price();
            }
        }

        return $cart_item_data;
    }

    /**
     * Restore custom data from session
     *
     * @param array $cart_item     Cart item
     * @param array $session_values Session values
     * @return array
     */
    public function get_cart_item_from_session(array $cart_item, array $session_values): array {
        $keys = [
            'treatpack_package_id',
            'treatpack_treatment_id',
            'treatpack_sessions',
            'treatpack_deposit_type',
            'treatpack_deposit_amount',
            'treatpack_balance_due',
            'treatpack_total_price',
        ];

        foreach ($keys as $key) {
            if (isset($session_values[$key])) {
                $cart_item[$key] = $session_values[$key];
            }
        }

        return $cart_item;
    }

    /**
     * Adjust cart prices for deposit payments
     *
     * @param \WC_Cart $cart Cart object
     */
    public function adjust_cart_prices(\WC_Cart $cart): void {
        if (is_admin() && !defined('DOING_AJAX')) {
            return;
        }

        if (did_action('woocommerce_before_calculate_totals') >= 2) {
            return;
        }

        foreach ($cart->get_cart() as $cart_item) {
            if (!isset($cart_item['treatpack_package_id'])) {
                continue;
            }

            $deposit_type = $cart_item['treatpack_deposit_type'] ?? 'none';

            // If deposit is enabled, set price to deposit amount
            if ($deposit_type !== 'none' && isset($cart_item['treatpack_deposit_amount'])) {
                $cart_item['data']->set_price($cart_item['treatpack_deposit_amount']);
            }
        }
    }

    /**
     * Display custom data in cart
     *
     * @param array $item_data Existing item data
     * @param array $cart_item Cart item
     * @return array
     */
    public function display_cart_item_data(array $item_data, array $cart_item): array {
        if (!isset($cart_item['treatpack_package_id'])) {
            return $item_data;
        }

        // Sessions
        if (isset($cart_item['treatpack_sessions'])) {
            $item_data[] = [
                'key' => __('Sessions', 'treatpack'),
                'value' => $cart_item['treatpack_sessions'],
            ];
        }

        // Deposit info
        $deposit_type = $cart_item['treatpack_deposit_type'] ?? 'none';

        if ($deposit_type !== 'none') {
            $item_data[] = [
                'key' => __('Payment Type', 'treatpack'),
                'value' => $deposit_type === 'pay_at_location'
                    ? __('Pay at location', 'treatpack')
                    : __('Deposit', 'treatpack'),
            ];

            if (isset($cart_item['treatpack_balance_due']) && $cart_item['treatpack_balance_due'] > 0) {
                $item_data[] = [
                    'key' => __('Balance Due', 'treatpack'),
                    'value' => wc_price($cart_item['treatpack_balance_due']),
                ];
            }
        }

        return $item_data;
    }

    /**
     * Modify cart item name to show treatment name
     *
     * @param string $name      Product name
     * @param array  $cart_item Cart item
     * @param string $cart_item_key Cart item key
     * @return string
     */
    public function modify_cart_item_name(string $name, array $cart_item, string $cart_item_key): string {
        if (!isset($cart_item['treatpack_treatment_id'])) {
            return $name;
        }

        $treatment = get_post($cart_item['treatpack_treatment_id']);

        if ($treatment) {
            $package = PackageRepository::find($cart_item['treatpack_package_id']);
            if ($package) {
                $name = sprintf(
                    '<strong>%s</strong><br><small>%s</small>',
                    esc_html($treatment->post_title),
                    esc_html($package->get_name())
                );
            }
        }

        return $name;
    }
}
