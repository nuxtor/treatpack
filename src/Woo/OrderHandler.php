<?php

namespace TreatmentPackages\Woo;

use TreatmentPackages\Packages\PackageRepository;
use TreatmentPackages\Customer\CustomerPackageRepository;

defined('ABSPATH') || exit;

/**
 * WooCommerce Order Handler
 *
 * Handles order completion and creates customer packages
 */
class OrderHandler {

    /**
     * Constructor
     */
    public function __construct() {
        // Save package data to order item
        add_action('woocommerce_checkout_create_order_line_item', [$this, 'save_order_item_meta'], 10, 4);

        // Create customer package on order completion
        add_action('woocommerce_order_status_completed', [$this, 'create_customer_package']);
        add_action('woocommerce_order_status_processing', [$this, 'create_customer_package']);

        // Handle order cancellation/refund
        add_action('woocommerce_order_status_cancelled', [$this, 'cancel_customer_package']);
        add_action('woocommerce_order_status_refunded', [$this, 'cancel_customer_package']);

        // Display package info in order details
        add_action('woocommerce_order_item_meta_end', [$this, 'display_order_item_meta'], 10, 3);

        // Admin order item display
        add_action('woocommerce_before_order_itemmeta', [$this, 'admin_order_item_meta'], 10, 3);
    }

    /**
     * Save package meta to order item
     *
     * @param \WC_Order_Item_Product $item          Order item
     * @param string                 $cart_item_key Cart item key
     * @param array                  $values        Cart item values
     * @param \WC_Order              $order         Order object
     */
    public function save_order_item_meta(\WC_Order_Item_Product $item, string $cart_item_key, array $values, \WC_Order $order): void {
        if (!isset($values['treatpack_package_id'])) {
            return;
        }

        $item->add_meta_data('_treatpack_package_id', $values['treatpack_package_id']);
        $item->add_meta_data('_treatpack_treatment_id', $values['treatpack_treatment_id']);
        $item->add_meta_data('_treatpack_sessions', $values['treatpack_sessions']);
        $item->add_meta_data('_treatpack_deposit_type', $values['treatpack_deposit_type']);
        $item->add_meta_data('_treatpack_deposit_amount', $values['treatpack_deposit_amount']);
        $item->add_meta_data('_treatpack_balance_due', $values['treatpack_balance_due']);
        $item->add_meta_data('_treatpack_total_price', $values['treatpack_total_price']);
    }

    /**
     * Create customer package when order is completed/processing
     *
     * @param int $order_id Order ID
     */
    public function create_customer_package(int $order_id): void {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        foreach ($order->get_items() as $item_id => $item) {
            $package_id = $item->get_meta('_treatpack_package_id');

            if (!$package_id) {
                continue;
            }

            // Check if already created
            $existing = CustomerPackageRepository::find_by_order_item($item_id);
            if ($existing) {
                continue;
            }

            $package = PackageRepository::find($package_id);
            $treatment = get_post($item->get_meta('_treatpack_treatment_id'));

            $data = [
                'customer_id' => $order->get_customer_id(),
                'order_id' => $order_id,
                'order_item_id' => $item_id,
                'package_id' => $package_id,
                'treatment_id' => $item->get_meta('_treatpack_treatment_id'),
                'package_name' => $package ? $package->get_name() : $item->get_name(),
                'treatment_name' => $treatment ? $treatment->post_title : __('Treatment', 'treatpack'),
                'total_sessions' => (int) $item->get_meta('_treatpack_sessions'),
                'sessions_used' => 0,
                'total_price' => (float) $item->get_meta('_treatpack_total_price'),
                'deposit_paid' => (float) $item->get_meta('_treatpack_deposit_amount'),
                'balance_due' => (float) $item->get_meta('_treatpack_balance_due'),
                'balance_paid' => 0,
                'status' => 'active',
            ];

            CustomerPackageRepository::create($data);
        }
    }

    /**
     * Cancel customer package when order is cancelled/refunded
     *
     * @param int $order_id Order ID
     */
    public function cancel_customer_package(int $order_id): void {
        $order = wc_get_order($order_id);

        if (!$order) {
            return;
        }

        foreach ($order->get_items() as $item_id => $item) {
            $package_id = $item->get_meta('_treatpack_package_id');

            if (!$package_id) {
                continue;
            }

            $customer_package = CustomerPackageRepository::find_by_order_item($item_id);

            if ($customer_package) {
                CustomerPackageRepository::update_status($customer_package['id'], 'cancelled');
            }
        }
    }

    /**
     * Display package info in order details (frontend)
     *
     * @param int                    $item_id Order item ID
     * @param \WC_Order_Item_Product $item    Order item
     * @param \WC_Order              $order   Order
     */
    public function display_order_item_meta(int $item_id, \WC_Order_Item_Product $item, \WC_Order $order): void {
        $package_id = $item->get_meta('_treatpack_package_id');

        if (!$package_id) {
            return;
        }

        $sessions = $item->get_meta('_treatpack_sessions');
        $balance_due = $item->get_meta('_treatpack_balance_due');

        echo '<div class="treatpack-order-item-info">';
        echo '<p><strong>' . esc_html__('Sessions:', 'treatpack') . '</strong> ' . esc_html($sessions) . '</p>';

        if ($balance_due > 0) {
            echo '<p><strong>' . esc_html__('Balance Due:', 'treatpack') . '</strong> ' . wc_price($balance_due) . '</p>';
        }

        // Show session usage for customer
        if (is_account_page()) {
            $customer_package = CustomerPackageRepository::find_by_order_item($item_id);
            if ($customer_package) {
                $remaining = $customer_package['total_sessions'] - $customer_package['sessions_used'];
                echo '<p><strong>' . esc_html__('Sessions Remaining:', 'treatpack') . '</strong> ' . esc_html($remaining) . '</p>';
            }
        }

        echo '</div>';
    }

    /**
     * Display package info in admin order
     *
     * @param int                    $item_id Order item ID
     * @param \WC_Order_Item_Product $item    Order item
     * @param \WC_Product|null       $product Product
     */
    public function admin_order_item_meta(int $item_id, $item, $product): void {
        if (!$item instanceof \WC_Order_Item_Product) {
            return;
        }

        $package_id = $item->get_meta('_treatpack_package_id');

        if (!$package_id) {
            return;
        }

        $customer_package = CustomerPackageRepository::find_by_order_item($item_id);

        if (!$customer_package) {
            return;
        }

        $remaining = $customer_package['total_sessions'] - $customer_package['sessions_used'];
        $status_labels = [
            'active' => __('Active', 'treatpack'),
            'completed' => __('Completed', 'treatpack'),
            'cancelled' => __('Cancelled', 'treatpack'),
            'expired' => __('Expired', 'treatpack'),
        ];

        echo '<div class="treatpack-admin-order-item" style="background: #f8f9fa; padding: 10px; margin: 10px 0; border-radius: 4px;">';
        echo '<strong>' . esc_html__('TreatPack Package', 'treatpack') . '</strong><br>';
        echo sprintf(
            __('Sessions: %d used / %d total (%d remaining)', 'treatpack'),
            $customer_package['sessions_used'],
            $customer_package['total_sessions'],
            $remaining
        );
        echo '<br>';
        echo sprintf(
            __('Status: %s', 'treatpack'),
            $status_labels[$customer_package['status']] ?? $customer_package['status']
        );

        if ($customer_package['balance_due'] > 0) {
            $balance_remaining = $customer_package['balance_due'] - $customer_package['balance_paid'];
            if ($balance_remaining > 0) {
                echo '<br><span style="color: #dc3545;">';
                echo sprintf(__('Outstanding Balance: %s', 'treatpack'), wc_price($balance_remaining));
                echo '</span>';
            }
        }

        echo '</div>';
    }
}
