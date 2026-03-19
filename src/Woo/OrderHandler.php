<?php
/**
 * WooCommerce Order Handler
 *
 * @package TreatmentPackages\Woo
 */

namespace TreatmentPackages\Woo;

use TreatmentPackages\Customer\CustomerPackagesRepository;

defined( 'ABSPATH' ) || exit;

/**
 * OrderHandler Class
 *
 * Handles WooCommerce order events to create customer package records
 * when orders containing treatment packages are completed.
 */
class OrderHandler {

    /**
     * Initialize the order handler
     */
    public static function init() {
        // Create customer package records when order is completed
        add_action( 'woocommerce_order_status_completed', array( __CLASS__, 'process_completed_order' ) );
        add_action( 'woocommerce_order_status_processing', array( __CLASS__, 'process_completed_order' ) );

        // Handle order cancellation/refund
        add_action( 'woocommerce_order_status_cancelled', array( __CLASS__, 'handle_order_cancelled' ) );
        add_action( 'woocommerce_order_status_refunded', array( __CLASS__, 'handle_order_cancelled' ) );
        add_action( 'woocommerce_order_status_failed', array( __CLASS__, 'handle_order_cancelled' ) );

        // Display package info in order details (admin)
        add_action( 'woocommerce_admin_order_item_headers', array( __CLASS__, 'add_admin_order_item_header' ) );
        add_action( 'woocommerce_admin_order_item_values', array( __CLASS__, 'add_admin_order_item_values' ), 10, 3 );

        // Display package info on thank you page
        add_action( 'woocommerce_thankyou', array( __CLASS__, 'display_thankyou_package_info' ), 5 );

        // Display in customer order details
        add_action( 'woocommerce_order_details_after_order_table', array( __CLASS__, 'display_customer_order_packages' ) );

        // Add order note when package is created
        add_action( 'tp_customer_package_created', array( __CLASS__, 'add_order_note_on_package_created' ), 10, 2 );
    }

    /**
     * Process completed order - create customer package records
     *
     * @param int $order_id WooCommerce order ID.
     */
    public static function process_completed_order( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        // Check if we've already processed this order
        $processed = $order->get_meta( '_tp_packages_processed' );
        if ( 'yes' === $processed ) {
            return;
        }

        $user_id = $order->get_user_id();

        // Process each order item
        foreach ( $order->get_items() as $item_id => $item ) {
            // Check if this item has package data
            $package_id = $item->get_meta( '_tp_package_id' );

            if ( ! $package_id ) {
                continue;
            }

            // Check if customer package already exists for this order item
            $existing = CustomerPackagesRepository::find_by_order_item( $item_id );
            if ( $existing ) {
                continue;
            }

            // Get package metadata from order item
            $treatment_id      = $item->get_meta( '_tp_treatment_id' );
            $treatment_name    = $item->get_meta( '_tp_treatment_name' );
            $package_name      = $item->get_meta( '_tp_package_name' );
            $sessions          = $item->get_meta( '_tp_sessions' );
            $total_price       = $item->get_meta( '_tp_total_price' );
            $deposit_amount    = $item->get_meta( '_tp_deposit_amount' );
            $remaining_balance = $item->get_meta( '_tp_remaining_balance' );
            $has_deposit       = $item->get_meta( '_tp_has_deposit' );

            // Calculate totals based on quantity
            $quantity = $item->get_quantity();
            $total_sessions = $sessions * $quantity;
            $total_full_price = $total_price * $quantity;
            $total_deposit = $deposit_amount * $quantity;
            $total_remaining = $remaining_balance * $quantity;

            // Build package name
            $full_package_name = $treatment_name . ' - ' . $package_name;
            if ( $quantity > 1 ) {
                $full_package_name .= ' (x' . $quantity . ')';
            }

            // Create customer package record
            $customer_package_id = CustomerPackagesRepository::create( array(
                'user_id'            => $user_id,
                'order_id'           => $order_id,
                'order_item_id'      => $item_id,
                'treatment_id'       => $treatment_id,
                'package_id'         => $package_id,
                'package_name'       => $full_package_name,
                'sessions_purchased' => $total_sessions,
                'sessions_remaining' => $total_sessions,
                'total_price'        => $total_full_price,
                'deposit_paid'       => $total_deposit,
                'remaining_balance'  => $total_remaining,
                'status'             => CustomerPackagesRepository::STATUS_ACTIVE,
                'notes'              => sprintf(
                    'Created from order #%d on %s',
                    $order_id,
                    current_time( 'Y-m-d H:i:s' )
                ),
            ) );

            if ( $customer_package_id ) {
                // Store reference in order item
                $item->add_meta_data( '_tp_customer_package_id', $customer_package_id, true );
                $item->save();

                /**
                 * Action fired after a customer package is created from an order
                 *
                 * @param int                       $customer_package_id Customer package ID.
                 * @param int                       $order_id            Order ID.
                 * @param \WC_Order_Item_Product    $item                Order item.
                 */
                do_action( 'tpd_order_package_created', $customer_package_id, $order_id, $item );
            }
        }

        // Mark order as processed
        $order->update_meta_data( '_tp_packages_processed', 'yes' );
        $order->save();
    }

    /**
     * Handle order cancellation - cancel associated customer packages
     *
     * @param int $order_id WooCommerce order ID.
     */
    public static function handle_order_cancelled( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        // Get all customer packages for this order
        $customer_packages = CustomerPackagesRepository::get_by_order( $order_id );

        foreach ( $customer_packages as $package ) {
            // Only cancel if active and no sessions have been used
            if ( CustomerPackagesRepository::STATUS_ACTIVE === $package->status &&
                 $package->sessions_remaining === $package->sessions_purchased ) {

                CustomerPackagesRepository::cancel(
                    $package->id,
                    sprintf( 'Order #%d was cancelled/refunded', $order_id )
                );
            }
        }
    }

    /**
     * Add header column in admin order items
     */
    public static function add_admin_order_item_header() {
        ?>
        <th class="tp-sessions-column"><?php esc_html_e( 'Sessions', 'treatpack' ); ?></th>
        <?php
    }

    /**
     * Add values column in admin order items
     *
     * @param \WC_Product|null       $product Product object.
     * @param \WC_Order_Item_Product $item    Order item.
     * @param int                    $item_id Order item ID.
     */
    public static function add_admin_order_item_values( $product, $item, $item_id ) {
        $sessions = $item->get_meta( '_tp_sessions' );
        $customer_package_id = $item->get_meta( '_tp_customer_package_id' );
        ?>
        <td class="tp-sessions-column">
            <?php if ( $sessions ) : ?>
                <strong><?php echo esc_html( $sessions ); ?></strong>
                <?php if ( $customer_package_id ) : ?>
                    <br>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=tp-customer-packages&id=' . $customer_package_id ) ); ?>" class="button button-small">
                        <?php esc_html_e( 'View Package', 'treatpack' ); ?>
                    </a>
                <?php endif; ?>
            <?php else : ?>
                &mdash;
            <?php endif; ?>
        </td>
        <?php
    }

    /**
     * Display package info on thank you page
     *
     * @param int $order_id Order ID.
     */
    public static function display_thankyou_package_info( $order_id ) {
        $order = wc_get_order( $order_id );

        if ( ! $order ) {
            return;
        }

        $customer_packages = CustomerPackagesRepository::get_by_order( $order_id );

        if ( empty( $customer_packages ) ) {
            return;
        }

        // Enqueue styles
        wp_enqueue_style( 'tp-deposits-frontend' );

        ?>
        <div class="tp-order-deposit-summary">
            <h3><?php esc_html_e( 'Your Treatment Packages', 'treatpack' ); ?></h3>

            <?php foreach ( $customer_packages as $package ) : ?>
                <div class="tp-package-summary-item" style="margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid rgba(255,255,255,0.2);">
                    <strong style="font-size: 1.1em;"><?php echo esc_html( $package->package_name ); ?></strong>

                    <div class="deposit-row" style="margin-top: 10px;">
                        <span><?php esc_html_e( 'Sessions Purchased:', 'treatpack' ); ?></span>
                        <span><?php echo esc_html( $package->sessions_purchased ); ?></span>
                    </div>

                    <?php if ( $package->remaining_balance > 0 ) : ?>
                        <div class="deposit-row">
                            <span><?php esc_html_e( 'Deposit Paid:', 'treatpack' ); ?></span>
                            <span><?php echo wp_kses_post( wc_price( $package->deposit_paid ) ); ?></span>
                     </div>
                        <div class="deposit-row">
                            <span><?php esc_html_e( 'Balance Due:', 'treatpack' ); ?></span>
                            <span><?php echo wp_kses_post( wc_price( $package->remaining_balance ) ); ?></span>
                        </div>
                    <?php else : ?>
                        <div class="deposit-row">
                            <span><?php esc_html_e( 'Amount Paid:', 'treatpack' ); ?></span>
                            <span><?php echo wp_kses_post( wc_price( $package->total_price ) ); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <p style="font-size: 0.9em; opacity: 0.9; margin-top: 15px;">
                <?php esc_html_e( 'You can view and manage your treatment packages in your account dashboard.', 'treatpack' ); ?>
            </p>
        </div>
        <?php
    }

    /**
     * Display packages in customer order details
     *
     * @param \WC_Order $order Order object.
     */
    public static function display_customer_order_packages( $order ) {
        $customer_packages = CustomerPackagesRepository::get_by_order( $order->get_id() );

        if ( empty( $customer_packages ) ) {
            return;
        }

        ?>
        <h2><?php esc_html_e( 'Treatment Package Details', 'treatpack' ); ?></h2>

        <table class="woocommerce-table woocommerce-table--order-details tp-packages-table-customer">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Package', 'treatpack' ); ?></th>
                    <th><?php esc_html_e( 'Sessions', 'treatpack' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'treatpack' ); ?></th>
                    <?php if ( array_sum( array_column( $customer_packages, 'remaining_balance' ) ) > 0 ) : ?>
                        <th><?php esc_html_e( 'Balance', 'treatpack' ); ?></th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $customer_packages as $package ) : ?>
                    <tr>
                        <td><?php echo esc_html( $package->package_name ); ?></td>
                        <td>
                            <?php
                            printf(
                                /* translators: 1: remaining sessions, 2: total sessions */
                                esc_html__( '%1$d of %2$d remaining', 'treatpack' ),
                                intval( $package->sessions_remaining ),
                                intval( $package->sessions_purchased )
                            );
                            ?>
                        </td>
                        <td>
                            <span class="tp-status tp-status-<?php echo esc_attr( $package->status ); ?>">
                                <?php echo esc_html( ucfirst( $package->status ) ); ?>
                            </span>
                        </td>
                        <?php if ( array_sum( array_column( $customer_packages, 'remaining_balance' ) ) > 0 ) : ?>
                            <td>
                                <?php if ( $package->remaining_balance > 0 ) : ?>
                                    <?php echo wp_kses_post( wc_price( $package->remaining_balance ) ); ?>
                                <?php else : ?>
                                    <span class="tp-paid-in-full"><?php esc_html_e( 'Paid', 'treatpack' ); ?></span>
                                <?php endif; ?>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <style>
            .tp-packages-table-customer {
                margin-top: 20px;
            }
            .tp-status {
                display: inline-block;
                padding: 3px 10px;
                border-radius: 3px;
                font-size: 12px;
                font-weight: 600;
                text-transform: uppercase;
            }
            .tp-status-active {
                background: #d4edda;
                color: #155724;
            }
            .tp-status-completed {
                background: #cce5ff;
                color: #004085;
            }
            .tp-status-cancelled {
                background: #f8d7da;
                color: #721c24;
            }
            .tp-paid-in-full {
                color: #28a745;
                font-weight: 600;
            }
        </style>
        <?php
    }

    /**
     * Add order note when customer package is created
     *
     * @param int   $customer_package_id Customer package ID.
     * @param array $data                Package data.
     */
    public static function add_order_note_on_package_created( $customer_package_id, $data ) {
        if ( empty( $data['order_id'] ) ) {
            return;
        }

        $order = wc_get_order( $data['order_id'] );

        if ( ! $order ) {
            return;
        }

        $note = sprintf(
            /* translators: 1: package name, 2: number of sessions, 3: customer package ID */
            __( 'Treatment package created: %1$s (%2$d sessions). Package ID: #%3$d', 'treatpack' ),
            $data['package_name'],
            $data['sessions_purchased'],
            $customer_package_id
        );

        if ( $data['remaining_balance'] > 0 ) {
            $note .= sprintf(
                /* translators: 1: deposit amount, 2: remaining balance */
                __( '. Deposit: %1$s, Balance due: %2$s', 'treatpack' ),
                wc_price( $data['deposit_paid'] ),
                wc_price( $data['remaining_balance'] )
            );
        }

        $order->add_order_note( $note );
    }

    /**
     * Check if an order contains treatment packages
     *
     * @param int|\WC_Order $order Order ID or object.
     * @return bool
     */
    public static function order_has_packages( $order ) {
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }

        if ( ! $order ) {
            return false;
        }

        foreach ( $order->get_items() as $item ) {
            if ( $item->get_meta( '_tp_package_id' ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get total deposit amount for an order
     *
     * @param int|\WC_Order $order Order ID or object.
     * @return float
     */
    public static function get_order_deposit_total( $order ) {
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }

        if ( ! $order ) {
            return 0.00;
        }

        $total = 0.00;

        foreach ( $order->get_items() as $item ) {
            $deposit = $item->get_meta( '_tp_deposit_amount' );
            if ( $deposit ) {
                $total += floatval( $deposit ) * $item->get_quantity();
            }
        }

        return $total;
    }

    /**
     * Get total remaining balance for an order
     *
     * @param int|\WC_Order $order Order ID or object.
     * @return float
     */
    public static function get_order_remaining_balance( $order ) {
        if ( is_numeric( $order ) ) {
            $order = wc_get_order( $order );
        }

        if ( ! $order ) {
            return 0.00;
        }

        $total = 0.00;

        foreach ( $order->get_items() as $item ) {
            $remaining = $item->get_meta( '_tp_remaining_balance' );
            if ( $remaining ) {
                $total += floatval( $remaining ) * $item->get_quantity();
            }
        }

        return $total;
    }
}
