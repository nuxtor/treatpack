<?php
/**
 * Admin Menu
 *
 * @package TreatmentPackages\Admin
 */

namespace TreatmentPackages\Admin;

use TreatmentPackages\Customer\CustomerPackagesRepository;
use TreatmentPackages\PostTypes\TreatmentPostType;

defined( 'ABSPATH' ) || exit;

/**
 * AdminMenu Class
 *
 * Registers admin menus and renders the customer packages management screen.
 */
class AdminMenu {

    /**
     * Initialize admin menu
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menus' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

        // AJAX handlers
        add_action( 'wp_ajax_tp_use_session', array( __CLASS__, 'ajax_use_session' ) );
        add_action( 'wp_ajax_tp_record_payment', array( __CLASS__, 'ajax_record_payment' ) );
        add_action( 'wp_ajax_tp_update_package_status', array( __CLASS__, 'ajax_update_status' ) );
    }

    /**
     * Register admin menus
     */
    public static function register_menus() {
        // Add submenu under Treatments
        add_submenu_page(
            'edit.php?post_type=' . TreatmentPostType::POST_TYPE,
            __( 'Customer Packages', 'treatment-packages-deposits' ),
            __( 'Customer Packages', 'treatment-packages-deposits' ),
            'manage_woocommerce',
            'tp-customer-packages',
            array( __CLASS__, 'render_customer_packages_page' )
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_admin_assets( $hook ) {
        if ( 'treatment_page_tp-customer-packages' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'tp-admin-customer-packages',
            TP_DEPOSITS_PLUGIN_URL . 'assets/css/admin-customer-packages.css',
            array(),
            TP_DEPOSITS_VERSION
        );

        wp_enqueue_script(
            'tp-admin-customer-packages',
            TP_DEPOSITS_PLUGIN_URL . 'assets/js/admin-customer-packages.js',
            array( 'jquery' ),
            TP_DEPOSITS_VERSION,
            true
        );

        wp_localize_script(
            'tp-admin-customer-packages',
            'tpAdminCustomer',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'tp_admin_customer' ),
                'i18n'    => array(
                    'confirmUseSession'  => __( 'Mark one session as used?', 'treatment-packages-deposits' ),
                    'confirmCancel'      => __( 'Are you sure you want to cancel this package?', 'treatment-packages-deposits' ),
                    'enterPaymentAmount' => __( 'Enter payment amount:', 'treatment-packages-deposits' ),
                    'success'            => __( 'Updated successfully!', 'treatment-packages-deposits' ),
                    'error'              => __( 'An error occurred. Please try again.', 'treatment-packages-deposits' ),
                ),
            )
        );
    }

    /**
     * Render customer packages page
     */
    public static function render_customer_packages_page() {
        // Check for single package view
        $package_id = isset( $_GET['id'] ) ? absint( $_GET['id'] ) : 0;

        if ( $package_id ) {
            self::render_single_package( $package_id );
            return;
        }

        // List view
        self::render_packages_list();
    }

    /**
     * Render packages list
     */
    private static function render_packages_list() {
        // Get filters
        $status_filter = isset( $_GET['status'] ) ? sanitize_text_field( $_GET['status'] ) : '';
        $user_filter = isset( $_GET['user_id'] ) ? absint( $_GET['user_id'] ) : 0;
        $search = isset( $_GET['s'] ) ? sanitize_text_field( $_GET['s'] ) : '';

        // Get packages
        $packages = self::get_filtered_packages( $status_filter, $user_filter, $search );

        // Get stats
        $stats = self::get_stats();
        ?>
        <div class="wrap tp-customer-packages-wrap">
            <h1 class="wp-heading-inline">
                <?php esc_html_e( 'Customer Packages', 'treatment-packages-deposits' ); ?>
            </h1>
            <hr class="wp-header-end">

            <!-- Stats Cards -->
            <div class="tp-stats-cards">
                <div class="tp-stat-card">
                    <span class="tp-stat-number"><?php echo esc_html( $stats['total_active'] ); ?></span>
                    <span class="tp-stat-label"><?php esc_html_e( 'Active Packages', 'treatment-packages-deposits' ); ?></span>
                </div>
                <div class="tp-stat-card">
                    <span class="tp-stat-number"><?php echo esc_html( $stats['total_sessions'] ); ?></span>
                    <span class="tp-stat-label"><?php esc_html_e( 'Sessions Remaining', 'treatment-packages-deposits' ); ?></span>
                </div>
                <div class="tp-stat-card">
                    <span class="tp-stat-number"><?php echo wc_price( $stats['total_balance'] ); ?></span>
                    <span class="tp-stat-label"><?php esc_html_e( 'Outstanding Balance', 'treatment-packages-deposits' ); ?></span>
                </div>
                <div class="tp-stat-card">
                    <span class="tp-stat-number"><?php echo esc_html( $stats['total_completed'] ); ?></span>
                    <span class="tp-stat-label"><?php esc_html_e( 'Completed', 'treatment-packages-deposits' ); ?></span>
                </div>
            </div>

            <!-- Filters -->
            <div class="tp-filters">
                <form method="get" action="">
                    <input type="hidden" name="post_type" value="<?php echo esc_attr( TreatmentPostType::POST_TYPE ); ?>">
                    <input type="hidden" name="page" value="tp-customer-packages">

                    <select name="status">
                        <option value=""><?php esc_html_e( 'All Statuses', 'treatment-packages-deposits' ); ?></option>
                        <option value="active" <?php selected( $status_filter, 'active' ); ?>><?php esc_html_e( 'Active', 'treatment-packages-deposits' ); ?></option>
                        <option value="completed" <?php selected( $status_filter, 'completed' ); ?>><?php esc_html_e( 'Completed', 'treatment-packages-deposits' ); ?></option>
                        <option value="cancelled" <?php selected( $status_filter, 'cancelled' ); ?>><?php esc_html_e( 'Cancelled', 'treatment-packages-deposits' ); ?></option>
                    </select>

                    <?php
                    // User dropdown
                    $users = get_users( array(
                        'meta_key' => '',
                        'orderby'  => 'display_name',
                        'order'    => 'ASC',
                        'number'   => 100,
                    ) );
                    ?>
                    <select name="user_id">
                        <option value=""><?php esc_html_e( 'All Customers', 'treatment-packages-deposits' ); ?></option>
                        <?php foreach ( $users as $user ) : ?>
                            <option value="<?php echo esc_attr( $user->ID ); ?>" <?php selected( $user_filter, $user->ID ); ?>>
                                <?php echo esc_html( $user->display_name . ' (' . $user->user_email . ')' ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( 'Search packages...', 'treatment-packages-deposits' ); ?>">

                    <button type="submit" class="button"><?php esc_html_e( 'Filter', 'treatment-packages-deposits' ); ?></button>

                    <?php if ( $status_filter || $user_filter || $search ) : ?>
                        <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=' . TreatmentPostType::POST_TYPE . '&page=tp-customer-packages' ) ); ?>" class="button">
                            <?php esc_html_e( 'Clear', 'treatment-packages-deposits' ); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Packages Table -->
            <table class="wp-list-table widefat fixed striped tp-packages-table">
                <thead>
                    <tr>
                        <th class="column-id"><?php esc_html_e( 'ID', 'treatment-packages-deposits' ); ?></th>
                        <th class="column-customer"><?php esc_html_e( 'Customer', 'treatment-packages-deposits' ); ?></th>
                        <th class="column-package"><?php esc_html_e( 'Package', 'treatment-packages-deposits' ); ?></th>
                        <th class="column-sessions"><?php esc_html_e( 'Sessions', 'treatment-packages-deposits' ); ?></th>
                        <th class="column-balance"><?php esc_html_e( 'Balance', 'treatment-packages-deposits' ); ?></th>
                        <th class="column-status"><?php esc_html_e( 'Status', 'treatment-packages-deposits' ); ?></th>
                        <th class="column-order"><?php esc_html_e( 'Order', 'treatment-packages-deposits' ); ?></th>
                        <th class="column-date"><?php esc_html_e( 'Date', 'treatment-packages-deposits' ); ?></th>
                        <th class="column-actions"><?php esc_html_e( 'Actions', 'treatment-packages-deposits' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ( ! empty( $packages ) ) : ?>
                        <?php foreach ( $packages as $package ) : ?>
                            <?php self::render_package_row( $package ); ?>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <tr>
                            <td colspan="9" class="tp-no-packages">
                                <?php esc_html_e( 'No customer packages found.', 'treatment-packages-deposits' ); ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /**
     * Render a single package row
     *
     * @param object $package Package data.
     */
    private static function render_package_row( $package ) {
        $user = get_user_by( 'id', $package->user_id );
        $view_url = add_query_arg( array(
            'post_type' => TreatmentPostType::POST_TYPE,
            'page'      => 'tp-customer-packages',
            'id'        => $package->id,
        ), admin_url( 'edit.php' ) );
        ?>
        <tr data-package-id="<?php echo esc_attr( $package->id ); ?>">
            <td class="column-id">
                <a href="<?php echo esc_url( $view_url ); ?>">
                    <strong>#<?php echo esc_html( $package->id ); ?></strong>
                </a>
            </td>
            <td class="column-customer">
                <?php if ( $user ) : ?>
                    <a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>">
                        <?php echo esc_html( $user->display_name ); ?>
                    </a>
                    <br>
                    <small><?php echo esc_html( $user->user_email ); ?></small>
                <?php else : ?>
                    <?php esc_html_e( 'Guest', 'treatment-packages-deposits' ); ?>
                <?php endif; ?>
            </td>
            <td class="column-package">
                <strong><?php echo esc_html( $package->package_name ); ?></strong>
            </td>
            <td class="column-sessions">
                <span class="tp-sessions-display">
                    <span class="remaining"><?php echo esc_html( $package->sessions_remaining ); ?></span>
                    <span class="separator">/</span>
                    <span class="total"><?php echo esc_html( $package->sessions_purchased ); ?></span>
                </span>
                <?php if ( $package->sessions_remaining > 0 && 'active' === $package->status ) : ?>
                    <button type="button" class="button button-small tp-use-session-btn" data-id="<?php echo esc_attr( $package->id ); ?>">
                        <?php esc_html_e( 'Use 1', 'treatment-packages-deposits' ); ?>
                    </button>
                <?php endif; ?>
            </td>
            <td class="column-balance">
                <?php if ( $package->remaining_balance > 0 ) : ?>
                    <span class="tp-balance-due"><?php echo wc_price( $package->remaining_balance ); ?></span>
                    <br>
                    <small><?php esc_html_e( 'of', 'treatment-packages-deposits' ); ?> <?php echo wc_price( $package->total_price ); ?></small>
                <?php else : ?>
                    <span class="tp-paid-full"><?php esc_html_e( 'Paid in full', 'treatment-packages-deposits' ); ?></span>
                <?php endif; ?>
            </td>
            <td class="column-status">
                <span class="tp-status tp-status-<?php echo esc_attr( $package->status ); ?>">
                    <?php echo esc_html( ucfirst( $package->status ) ); ?>
                </span>
            </td>
            <td class="column-order">
                <?php if ( $package->order_id ) : ?>
                    <a href="<?php echo esc_url( get_edit_post_link( $package->order_id ) ); ?>">
                        #<?php echo esc_html( $package->order_id ); ?>
                    </a>
                <?php else : ?>
                    &mdash;
                <?php endif; ?>
            </td>
            <td class="column-date">
                <?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $package->created_at ) ) ); ?>
            </td>
            <td class="column-actions">
                <a href="<?php echo esc_url( $view_url ); ?>" class="button button-small">
                    <?php esc_html_e( 'View', 'treatment-packages-deposits' ); ?>
                </a>
            </td>
        </tr>
        <?php
    }

    /**
     * Render single package view
     *
     * @param int $package_id Package ID.
     */
    private static function render_single_package( $package_id ) {
        $package = CustomerPackagesRepository::find( $package_id );

        if ( ! $package ) {
            wp_die( esc_html__( 'Package not found.', 'treatment-packages-deposits' ) );
        }

        $user = get_user_by( 'id', $package->user_id );
        $treatment = get_post( $package->treatment_id );
        $order = $package->order_id ? wc_get_order( $package->order_id ) : null;

        $back_url = admin_url( 'edit.php?post_type=' . TreatmentPostType::POST_TYPE . '&page=tp-customer-packages' );
        ?>
        <div class="wrap tp-single-package-wrap">
            <h1 class="wp-heading-inline">
                <a href="<?php echo esc_url( $back_url ); ?>" class="tp-back-link">
                    &larr; <?php esc_html_e( 'Back to Packages', 'treatment-packages-deposits' ); ?>
                </a>
                <?php
                printf(
                    /* translators: %d: package ID */
                    esc_html__( 'Package #%d', 'treatment-packages-deposits' ),
                    $package->id
                );
                ?>
                <span class="tp-status tp-status-<?php echo esc_attr( $package->status ); ?>">
                    <?php echo esc_html( ucfirst( $package->status ) ); ?>
                </span>
            </h1>
            <hr class="wp-header-end">

            <div class="tp-package-details-grid">
                <!-- Main Info -->
                <div class="tp-detail-card tp-main-info">
                    <h2><?php echo esc_html( $package->package_name ); ?></h2>

                    <div class="tp-sessions-big">
                        <div class="tp-sessions-remaining">
                            <span class="number"><?php echo esc_html( $package->sessions_remaining ); ?></span>
                            <span class="label"><?php esc_html_e( 'Sessions Remaining', 'treatment-packages-deposits' ); ?></span>
                        </div>
                        <div class="tp-sessions-total">
                            <?php
                            printf(
                                /* translators: %d: total sessions */
                                esc_html__( 'of %d purchased', 'treatment-packages-deposits' ),
                                $package->sessions_purchased
                            );
                            ?>
                        </div>
                    </div>

                    <?php if ( 'active' === $package->status && $package->sessions_remaining > 0 ) : ?>
                        <div class="tp-actions-box">
                            <button type="button" class="button button-primary button-large tp-use-session-btn" data-id="<?php echo esc_attr( $package->id ); ?>">
                                <?php esc_html_e( 'Use Session', 'treatment-packages-deposits' ); ?>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Financial Info -->
                <div class="tp-detail-card tp-financial-info">
                    <h3><?php esc_html_e( 'Financial Details', 'treatment-packages-deposits' ); ?></h3>

                    <table class="tp-detail-table">
                        <tr>
                            <th><?php esc_html_e( 'Total Price', 'treatment-packages-deposits' ); ?></th>
                            <td><?php echo wc_price( $package->total_price ); ?></td>
                        </tr>
                        <tr>
                            <th><?php esc_html_e( 'Deposit Paid', 'treatment-packages-deposits' ); ?></th>
                            <td><?php echo wc_price( $package->deposit_paid ); ?></td>
                        </tr>
                        <tr class="tp-balance-row <?php echo $package->remaining_balance > 0 ? 'has-balance' : ''; ?>">
                            <th><?php esc_html_e( 'Remaining Balance', 'treatment-packages-deposits' ); ?></th>
                            <td>
                                <?php if ( $package->remaining_balance > 0 ) : ?>
                                    <strong><?php echo wc_price( $package->remaining_balance ); ?></strong>
                                <?php else : ?>
                                    <span class="tp-paid-full"><?php esc_html_e( 'Paid in full', 'treatment-packages-deposits' ); ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>

                    <?php if ( $package->remaining_balance > 0 && 'active' === $package->status ) : ?>
                        <div class="tp-record-payment">
                            <h4><?php esc_html_e( 'Record Payment', 'treatment-packages-deposits' ); ?></h4>
                            <div class="tp-payment-form">
                                <input type="number" id="tp-payment-amount" step="0.01" min="0" max="<?php echo esc_attr( $package->remaining_balance ); ?>" placeholder="0.00">
                                <button type="button" class="button tp-record-payment-btn" data-id="<?php echo esc_attr( $package->id ); ?>">
                                    <?php esc_html_e( 'Record Payment', 'treatment-packages-deposits' ); ?>
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Customer Info -->
                <div class="tp-detail-card tp-customer-info">
                    <h3><?php esc_html_e( 'Customer', 'treatment-packages-deposits' ); ?></h3>

                    <?php if ( $user ) : ?>
                        <p>
                            <strong><?php echo esc_html( $user->display_name ); ?></strong><br>
                            <a href="mailto:<?php echo esc_attr( $user->user_email ); ?>"><?php echo esc_html( $user->user_email ); ?></a>
                        </p>
                        <p>
                            <a href="<?php echo esc_url( get_edit_user_link( $user->ID ) ); ?>" class="button button-small">
                                <?php esc_html_e( 'View Customer', 'treatment-packages-deposits' ); ?>
                            </a>
                        </p>
                    <?php else : ?>
                        <p><?php esc_html_e( 'Guest Customer', 'treatment-packages-deposits' ); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Order Info -->
                <div class="tp-detail-card tp-order-info">
                    <h3><?php esc_html_e( 'Order', 'treatment-packages-deposits' ); ?></h3>

                    <?php if ( $order ) : ?>
                        <p>
                            <a href="<?php echo esc_url( get_edit_post_link( $order->get_id() ) ); ?>">
                                <strong><?php echo esc_html( $order->get_order_number() ); ?></strong>
                            </a>
                            <br>
                            <?php echo esc_html( wc_format_datetime( $order->get_date_created() ) ); ?>
                        </p>
                    <?php else : ?>
                        <p><?php esc_html_e( 'No order linked', 'treatment-packages-deposits' ); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Treatment Info -->
                <div class="tp-detail-card tp-treatment-info">
                    <h3><?php esc_html_e( 'Treatment', 'treatment-packages-deposits' ); ?></h3>

                    <?php if ( $treatment ) : ?>
                        <p>
                            <a href="<?php echo esc_url( get_edit_post_link( $treatment->ID ) ); ?>">
                                <strong><?php echo esc_html( $treatment->post_title ); ?></strong>
                            </a>
                        </p>
                    <?php else : ?>
                        <p><?php esc_html_e( 'Treatment not found', 'treatment-packages-deposits' ); ?></p>
                    <?php endif; ?>
                </div>

                <!-- Notes/History -->
                <div class="tp-detail-card tp-notes-info">
                    <h3><?php esc_html_e( 'Notes & History', 'treatment-packages-deposits' ); ?></h3>
                    <div class="tp-notes-content">
                        <?php if ( ! empty( $package->notes ) ) : ?>
                            <pre><?php echo esc_html( $package->notes ); ?></pre>
                        <?php else : ?>
                            <p class="tp-no-notes"><?php esc_html_e( 'No notes yet.', 'treatment-packages-deposits' ); ?></p>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Status Actions -->
                <?php if ( 'active' === $package->status ) : ?>
                    <div class="tp-detail-card tp-status-actions">
                        <h3><?php esc_html_e( 'Actions', 'treatment-packages-deposits' ); ?></h3>
                        <p>
                            <button type="button" class="button tp-cancel-package-btn" data-id="<?php echo esc_attr( $package->id ); ?>">
                                <?php esc_html_e( 'Cancel Package', 'treatment-packages-deposits' ); ?>
                            </button>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Get filtered packages
     *
     * @param string $status  Status filter.
     * @param int    $user_id User ID filter.
     * @param string $search  Search term.
     * @return array
     */
    private static function get_filtered_packages( $status, $user_id, $search ) {
        global $wpdb;

        $table = $wpdb->prefix . 'tp_customer_packages';

        $sql = "SELECT * FROM {$table} WHERE 1=1";
        $params = array();

        if ( ! empty( $status ) ) {
            $sql .= ' AND status = %s';
            $params[] = $status;
        }

        if ( $user_id > 0 ) {
            $sql .= ' AND user_id = %d';
            $params[] = $user_id;
        }

        if ( ! empty( $search ) ) {
            $sql .= ' AND package_name LIKE %s';
            $params[] = '%' . $wpdb->esc_like( $search ) . '%';
        }

        $sql .= ' ORDER BY created_at DESC LIMIT 100';

        if ( ! empty( $params ) ) {
            return $wpdb->get_results( $wpdb->prepare( $sql, $params ) );
        }

        return $wpdb->get_results( $sql );
    }

    /**
     * Get stats for dashboard
     *
     * @return array
     */
    private static function get_stats() {
        global $wpdb;

        $table = $wpdb->prefix . 'tp_customer_packages';

        return array(
            'total_active'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'active'" ),
            'total_completed' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE status = 'completed'" ),
            'total_sessions'  => (int) $wpdb->get_var( "SELECT SUM(sessions_remaining) FROM {$table} WHERE status = 'active'" ) ?: 0,
            'total_balance'   => (float) $wpdb->get_var( "SELECT SUM(remaining_balance) FROM {$table} WHERE status = 'active'" ) ?: 0,
        );
    }

    /**
     * AJAX: Use a session
     */
    public static function ajax_use_session() {
        check_ajax_referer( 'tp_admin_customer', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'treatment-packages-deposits' ) ) );
        }

        $package_id = isset( $_POST['package_id'] ) ? absint( $_POST['package_id'] ) : 0;
        $notes = isset( $_POST['notes'] ) ? sanitize_textarea_field( $_POST['notes'] ) : '';

        if ( ! $package_id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid package.', 'treatment-packages-deposits' ) ) );
        }

        $result = CustomerPackagesRepository::use_session( $package_id, $notes );

        if ( false === $result ) {
            wp_send_json_error( array( 'message' => __( 'Could not use session.', 'treatment-packages-deposits' ) ) );
        }

        wp_send_json_success( array(
            'message'            => __( 'Session marked as used.', 'treatment-packages-deposits' ),
            'sessions_remaining' => $result['sessions_remaining'],
            'status'             => $result['status'],
        ) );
    }

    /**
     * AJAX: Record a payment
     */
    public static function ajax_record_payment() {
        check_ajax_referer( 'tp_admin_customer', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'treatment-packages-deposits' ) ) );
        }

        $package_id = isset( $_POST['package_id'] ) ? absint( $_POST['package_id'] ) : 0;
        $amount = isset( $_POST['amount'] ) ? floatval( $_POST['amount'] ) : 0;

        if ( ! $package_id || $amount <= 0 ) {
            wp_send_json_error( array( 'message' => __( 'Invalid package or amount.', 'treatment-packages-deposits' ) ) );
        }

        $new_balance = CustomerPackagesRepository::record_payment( $package_id, $amount );

        if ( false === $new_balance ) {
            wp_send_json_error( array( 'message' => __( 'Could not record payment.', 'treatment-packages-deposits' ) ) );
        }

        wp_send_json_success( array(
            'message'           => __( 'Payment recorded successfully.', 'treatment-packages-deposits' ),
            'remaining_balance' => $new_balance,
            'formatted_balance' => wc_price( $new_balance ),
        ) );
    }

    /**
     * AJAX: Update package status
     */
    public static function ajax_update_status() {
        check_ajax_referer( 'tp_admin_customer', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'treatment-packages-deposits' ) ) );
        }

        $package_id = isset( $_POST['package_id'] ) ? absint( $_POST['package_id'] ) : 0;
        $status = isset( $_POST['status'] ) ? sanitize_text_field( $_POST['status'] ) : '';
        $reason = isset( $_POST['reason'] ) ? sanitize_textarea_field( $_POST['reason'] ) : '';

        if ( ! $package_id || ! $status ) {
            wp_send_json_error( array( 'message' => __( 'Invalid package or status.', 'treatment-packages-deposits' ) ) );
        }

        if ( 'cancelled' === $status ) {
            $result = CustomerPackagesRepository::cancel( $package_id, $reason );
        } else {
            $result = CustomerPackagesRepository::update( $package_id, array( 'status' => $status ) );
        }

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Could not update status.', 'treatment-packages-deposits' ) ) );
        }

        wp_send_json_success( array(
            'message' => __( 'Status updated successfully.', 'treatment-packages-deposits' ),
            'status'  => $status,
        ) );
    }
}
