<?php
/**
 * WooCommerce Cart Handler
 *
 * @package TreatmentPackages\Woo
 */

namespace TreatmentPackages\Woo;

use TreatmentPackages\Packages\PackageModel;
use TreatmentPackages\Packages\PackageRepository;

defined( 'ABSPATH' ) || exit;

/**
 * CartHandler Class
 *
 * Handles cart modifications for treatment packages with deposits.
 * Adjusts prices to deposit amounts and stores package metadata.
 */
class CartHandler {

    /**
     * Initialize the cart handler
     */
    public static function init() {
        // Add package data when adding to cart
        add_filter( 'woocommerce_add_cart_item_data', array( __CLASS__, 'add_cart_item_data' ), 10, 3 );

        // Adjust cart item price to deposit amount
        add_action( 'woocommerce_before_calculate_totals', array( __CLASS__, 'adjust_cart_prices' ), 20 );

        // Display package info in cart
        add_filter( 'woocommerce_get_item_data', array( __CLASS__, 'display_cart_item_data' ), 10, 2 );

        // Add package data to order item
        add_action( 'woocommerce_checkout_create_order_line_item', array( __CLASS__, 'add_order_item_meta' ), 10, 4 );

        // Custom add to cart via AJAX
        add_action( 'wp_ajax_tp_add_to_cart', array( __CLASS__, 'ajax_add_to_cart' ) );
        add_action( 'wp_ajax_nopriv_tp_add_to_cart', array( __CLASS__, 'ajax_add_to_cart' ) );

        // Modify cart item name to show deposit info
        add_filter( 'woocommerce_cart_item_name', array( __CLASS__, 'modify_cart_item_name' ), 10, 3 );

        // Show deposit breakdown in cart totals
        add_action( 'woocommerce_cart_totals_after_order_total', array( __CLASS__, 'display_deposit_summary' ) );
        add_action( 'woocommerce_review_order_after_order_total', array( __CLASS__, 'display_deposit_summary' ) );
    }

    /**
     * Add package data to cart item when adding to cart
     *
     * @param array $cart_item_data Cart item data.
     * @param int   $product_id     Product ID.
     * @param int   $variation_id   Variation ID.
     * @return array Modified cart item data.
     */
    public static function add_cart_item_data( $cart_item_data, $product_id, $variation_id ) {
        // Check if this is a package product
        if ( ! ProductsSync::is_package_product( $product_id ) ) {
            return $cart_item_data;
        }

        // Get package data
        $package = ProductsSync::get_package_from_product( $product_id );

        if ( ! $package ) {
            return $cart_item_data;
        }

        // Calculate deposit
        $total_price = $package->get_total_price();
        $deposit_amount = $package->calculate_deposit();
        $remaining_balance = $package->calculate_remaining_balance();

        // Store package data in cart item
        $cart_item_data['tp_package_data'] = array(
            'package_id'        => $package->get_id(),
            'treatment_id'      => $package->get_treatment_id(),
            'treatment_name'    => $package->get_treatment_title(),
            'package_name'      => $package->get_display_name(),
            'sessions'          => $package->get_sessions(),
            'total_price'       => $total_price,
            'deposit_type'      => $package->get_deposit_type(),
            'deposit_value'     => $package->get_deposit_value(),
            'deposit_amount'    => $deposit_amount,
            'remaining_balance' => $remaining_balance,
            'has_deposit'       => $package->has_deposit(),
        );

        // Generate unique key to prevent merging items
        $cart_item_data['unique_key'] = md5( microtime() . wp_rand() );

        return $cart_item_data;
    }

    /**
     * Adjust cart item prices to deposit amount
     *
     * @param \WC_Cart $cart Cart object.
     */
    public static function adjust_cart_prices( $cart ) {
        if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
            return;
        }

        if ( did_action( 'woocommerce_before_calculate_totals' ) >= 2 ) {
            return;
        }

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( ! isset( $cart_item['tp_package_data'] ) ) {
                continue;
            }

            $package_data = $cart_item['tp_package_data'];

            // If package has deposit, set price to deposit amount
            if ( $package_data['has_deposit'] ) {
                $cart_item['data']->set_price( $package_data['deposit_amount'] );
            }
        }
    }

    /**
     * Display package data in cart
     *
     * @param array $item_data Existing item data.
     * @param array $cart_item Cart item.
     * @return array Modified item data.
     */
    public static function display_cart_item_data( $item_data, $cart_item ) {
        if ( ! isset( $cart_item['tp_package_data'] ) ) {
            return $item_data;
        }

        $package_data = $cart_item['tp_package_data'];

        // Sessions
        $item_data[] = array(
            'key'   => __( 'Sessions', 'treatpack' ),
            'value' => $package_data['sessions'],
        );

        // Show deposit breakdown if applicable
        if ( $package_data['has_deposit'] ) {
            $item_data[] = array(
                'key'   => __( 'Full Price', 'treatpack' ),
                'value' => wc_price( $package_data['total_price'] ),
            );

            $item_data[] = array(
                'key'   => __( 'Deposit (Pay Now)', 'treatpack' ),
                'value' => wc_price( $package_data['deposit_amount'] ),
            );

            $item_data[] = array(
                'key'   => __( 'Remaining Balance', 'treatpack' ),
                'value' => wc_price( $package_data['remaining_balance'] ),
            );
        }

        return $item_data;
    }

    /**
     * Modify cart item name to indicate deposit
     *
     * @param string $name      Product name.
     * @param array  $cart_item Cart item data.
     * @param string $cart_item_key Cart item key.
     * @return string Modified name.
     */
    public static function modify_cart_item_name( $name, $cart_item, $cart_item_key ) {
        if ( ! isset( $cart_item['tp_package_data'] ) ) {
            return $name;
        }

        $package_data = $cart_item['tp_package_data'];

        if ( $package_data['has_deposit'] ) {
            $name .= ' <span class="tp-deposit-badge">' . esc_html__( '(Deposit)', 'treatpack' ) . '</span>';
        }

        return $name;
    }

    /**
     * Add package data to order item meta
     *
     * @param \WC_Order_Item_Product $item          Order item.
     * @param string                 $cart_item_key Cart item key.
     * @param array                  $values        Cart item values.
     * @param \WC_Order              $order         Order object.
     */
    public static function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
        if ( ! isset( $values['tp_package_data'] ) ) {
            return;
        }

        $package_data = $values['tp_package_data'];

        // Store all package data as order item meta
        $item->add_meta_data( '_tp_package_id', $package_data['package_id'] );
        $item->add_meta_data( '_tp_treatment_id', $package_data['treatment_id'] );
        $item->add_meta_data( '_tp_treatment_name', $package_data['treatment_name'] );
        $item->add_meta_data( '_tp_package_name', $package_data['package_name'] );
        $item->add_meta_data( '_tp_sessions', $package_data['sessions'] );
        $item->add_meta_data( '_tp_total_price', $package_data['total_price'] );
        $item->add_meta_data( '_tp_deposit_amount', $package_data['deposit_amount'] );
        $item->add_meta_data( '_tp_remaining_balance', $package_data['remaining_balance'] );
        $item->add_meta_data( '_tp_has_deposit', $package_data['has_deposit'] ? 'yes' : 'no' );

        // Add visible meta for admin
        if ( $package_data['has_deposit'] ) {
            $item->add_meta_data( __( 'Full Price', 'treatpack' ), wc_price( $package_data['total_price'] ) );
            $item->add_meta_data( __( 'Deposit Paid', 'treatpack' ), wc_price( $package_data['deposit_amount'] ) );
            $item->add_meta_data( __( 'Balance Due', 'treatpack' ), wc_price( $package_data['remaining_balance'] ) );
        }

        $item->add_meta_data( __( 'Sessions', 'treatpack' ), $package_data['sessions'] );
    }

    /**
     * Display deposit summary in cart/checkout totals
     */
    public static function display_deposit_summary() {
        $cart = WC()->cart;

        if ( ! $cart ) {
            return;
        }

        $total_full_price = 0;
        $total_deposit = 0;
        $total_remaining = 0;
        $has_deposits = false;

        foreach ( $cart->get_cart() as $cart_item ) {
            if ( ! isset( $cart_item['tp_package_data'] ) ) {
                continue;
            }

            $package_data = $cart_item['tp_package_data'];
            $quantity = $cart_item['quantity'];

            if ( $package_data['has_deposit'] ) {
                $has_deposits = true;
                $total_full_price += $package_data['total_price'] * $quantity;
                $total_deposit += $package_data['deposit_amount'] * $quantity;
                $total_remaining += $package_data['remaining_balance'] * $quantity;
            }
        }

        if ( ! $has_deposits ) {
            return;
        }

        ?>
        <tr class="tp-deposit-summary">
            <th colspan="2">
                <strong><?php esc_html_e( 'Treatment Package Summary', 'treatpack' ); ?></strong>
            </th>
        </tr>
        <tr class="tp-deposit-full-price">
            <th><?php esc_html_e( 'Full Package Price', 'treatpack' ); ?></th>
            <td data-title="<?php esc_attr_e( 'Full Package Price', 'treatpack' ); ?>">
                <?php echo wp_kses_post( wc_price( $total_full_price ) ); ?>
            </td>
        </tr>
        <tr class="tp-deposit-amount">
            <th><?php esc_html_e( 'Deposit (Paying Today)', 'treatpack' ); ?></th>
            <td data-title="<?php esc_attr_e( 'Deposit', 'treatpack' ); ?>">
                <strong><?php echo wp_kses_post( wc_price( $total_deposit ) ); ?></strong>
            </td>
        </tr>
        <tr class="tp-deposit-remaining">
            <th><?php esc_html_e( 'Remaining Balance', 'treatpack' ); ?></th>
            <td data-title="<?php esc_attr_e( 'Remaining Balance', 'treatpack' ); ?>">
                <?php echo wp_kses_post( wc_price( $total_remaining ) ); ?>
                <small class="tp-balance-note">
                    <?php esc_html_e( '(Due at appointment)', 'treatpack' ); ?>
                </small>
            </td>
        </tr>
        <?php
    }

    /**
     * AJAX handler to add package to cart
     */
    public static function ajax_add_to_cart() {
        check_ajax_referer( 'tp_deposits_nonce', 'nonce' );

        // Ensure WooCommerce is loaded
        if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
            wp_send_json_error( array(
                'message' => __( 'WooCommerce is not available.', 'treatpack' ),
            ) );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        $package_id = isset( $_POST['package_id'] ) ? absint( $_POST['package_id'] ) : 0;
        $quantity = isset( $_POST['quantity'] ) ? absint( $_POST['quantity'] ) : 1;

        if ( ! $product_id ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid product.', 'treatpack' ),
            ) );
        }

        // Verify product exists and is a package product
        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            wp_send_json_error( array(
                'message' => __( 'Product not found.', 'treatpack' ),
            ) );
        }

        // Add to cart
        $cart_item_key = WC()->cart->add_to_cart( $product_id, $quantity );

        if ( ! $cart_item_key ) {
            wp_send_json_error( array(
                'message' => __( 'Could not add to cart. Please try again.', 'treatpack' ),
            ) );
        }

        // Get cart fragments for mini-cart update
        ob_start();
        woocommerce_mini_cart();
        $mini_cart = ob_get_clean();

        $fragments = array(
            'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini_cart . '</div>',
        );

        // Get cart hash
        $cart_hash = WC()->cart->get_cart_hash();

        wp_send_json_success( array(
            'message'    => __( 'Product added to cart.', 'treatpack' ),
            'fragments'  => apply_filters( 'woocommerce_add_to_cart_fragments', $fragments ), // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce core hook.
            'cart_hash'  => $cart_hash,
            'cart_count' => WC()->cart->get_cart_contents_count(),
        ) );
    }

    /**
     * Get cart items that are treatment packages
     *
     * @return array Array of cart items with package data.
     */
    public static function get_package_cart_items() {
        $cart = WC()->cart;

        if ( ! $cart ) {
            return array();
        }

        $package_items = array();

        foreach ( $cart->get_cart() as $cart_item_key => $cart_item ) {
            if ( isset( $cart_item['tp_package_data'] ) ) {
                $package_items[ $cart_item_key ] = $cart_item;
            }
        }

        return $package_items;
    }

    /**
     * Calculate total deposits in cart
     *
     * @return float Total deposit amount.
     */
    public static function get_cart_total_deposit() {
        $total = 0;

        foreach ( self::get_package_cart_items() as $cart_item ) {
            if ( $cart_item['tp_package_data']['has_deposit'] ) {
                $total += $cart_item['tp_package_data']['deposit_amount'] * $cart_item['quantity'];
            }
        }

        return $total;
    }

    /**
     * Calculate total remaining balance in cart
     *
     * @return float Total remaining balance.
     */
    public static function get_cart_total_remaining() {
        $total = 0;

        foreach ( self::get_package_cart_items() as $cart_item ) {
            if ( $cart_item['tp_package_data']['has_deposit'] ) {
                $total += $cart_item['tp_package_data']['remaining_balance'] * $cart_item['quantity'];
            }
        }

        return $total;
    }

    /**
     * Check if cart contains any deposit items
     *
     * @return bool
     */
    public static function cart_has_deposits() {
        foreach ( self::get_package_cart_items() as $cart_item ) {
            if ( $cart_item['tp_package_data']['has_deposit'] ) {
                return true;
            }
        }

        return false;
    }
}
