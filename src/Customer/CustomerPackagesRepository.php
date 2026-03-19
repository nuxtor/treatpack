<?php
/**
 * Customer Packages Repository
 *
 * @package TreatmentPackages\Customer
 */

namespace TreatmentPackages\Customer;

defined( 'ABSPATH' ) || exit;

/**
 * CustomerPackagesRepository Class
 *
 * Handles CRUD operations for customer purchased packages (sessions tracking).
 */
class CustomerPackagesRepository {

    /**
     * Table name (without prefix)
     *
     * @var string
     */
    const TABLE_NAME = 'tp_customer_packages';

    /**
     * Cache group
     *
     * @var string
     */
    const CACHE_GROUP = 'tpd_customer_packages';

    /**
     * Package statuses
     */
    const STATUS_ACTIVE    = 'active';
    const STATUS_COMPLETED = 'completed';
    const STATUS_CANCELLED = 'cancelled';
    const STATUS_EXPIRED   = 'expired';

    /**
     * Get the full table name with prefix
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Invalidate all caches for a given user (and optionally specific keys).
     *
     * @param int $user_id User ID.
     * @return void
     */
    private static function invalidate_cache( $user_id = 0 ) {
        wp_cache_flush_group( self::CACHE_GROUP );
    }

    /**
     * Find a customer package by ID
     *
     * @param int $id Customer package ID.
     * @return object|null
     */
    public static function find( $id ) {
        global $wpdb;

        $cache_key = 'find_' . $id;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached ) {
            return $cached;
        }

        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query.
        $result = $wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a safe prefixed table name.
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $id
            )
        );

        wp_cache_set( $cache_key, $result, self::CACHE_GROUP );

        return $result;
    }

    /**
     * Find customer package by order item ID
     *
     * @param int $order_item_id WooCommerce order item ID.
     * @return object|null
     */
    public static function find_by_order_item( $order_item_id ) {
        global $wpdb;

        $cache_key = 'find_by_order_item_' . $order_item_id;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached ) {
            return $cached;
        }

        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query.
        $result = $wpdb->get_row(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a safe prefixed table name.
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE order_item_id = %d",
                $order_item_id
            )
        );

        wp_cache_set( $cache_key, $result, self::CACHE_GROUP );

        return $result;
    }

    /**
     * Get all packages for a user
     *
     * @param int   $user_id User ID.
     * @param array $args    Optional. Query arguments.
     * @return array
     */
    public static function get_by_user( $user_id, $args = array() ) {
        global $wpdb;

        $defaults = array(
            'status'  => '',
            'orderby' => 'created_at',
            'order'   => 'DESC',
            'limit'   => -1,
            'offset'  => 0,
        );

        $args = wp_parse_args( $args, $defaults );

        $cache_key = 'get_by_user_' . $user_id . '_' . md5( wp_json_encode( $args ) );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached ) {
            return $cached;
        }

        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a safe prefixed table name.
        $sql = "SELECT * FROM {$table} WHERE user_id = %d";
        $params = array( $user_id );

        // Filter by status
        if ( ! empty( $args['status'] ) ) {
            if ( is_array( $args['status'] ) ) {
                $placeholders = implode( ', ', array_fill( 0, count( $args['status'] ), '%s' ) );
                $sql .= " AND status IN ({$placeholders})";
                $params = array_merge( $params, $args['status'] );
            } else {
                $sql .= ' AND status = %s';
                $params[] = $args['status'];
            }
        }

        // Order
        $allowed_orderby = array( 'id', 'created_at', 'updated_at', 'sessions_remaining', 'treatment_id' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
        $order = 'ASC' === strtoupper( $args['order'] ) ? 'ASC' : 'DESC';
        $sql .= " ORDER BY {$orderby} {$order}";

        // Limit
        if ( $args['limit'] > 0 ) {
            $sql .= ' LIMIT %d OFFSET %d';
            $params[] = $args['limit'];
            $params[] = $args['offset'];
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query.
        $results = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is built safely with placeholders above.
            $wpdb->prepare( $sql, $params )
        );

        wp_cache_set( $cache_key, $results, self::CACHE_GROUP );

        return $results;
    }

    /**
     * Get active packages for a user
     *
     * @param int $user_id User ID.
     * @return array
     */
    public static function get_active_by_user( $user_id ) {
        return self::get_by_user( $user_id, array(
            'status' => self::STATUS_ACTIVE,
        ) );
    }

    /**
     * Get all packages for an order
     *
     * @param int $order_id WooCommerce order ID.
     * @return array
     */
    public static function get_by_order( $order_id ) {
        global $wpdb;

        $cache_key = 'get_by_order_' . $order_id;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached ) {
            return $cached;
        }

        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query.
        $results = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a safe prefixed table name.
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE order_id = %d ORDER BY id ASC",
                $order_id
            )
        );

        wp_cache_set( $cache_key, $results, self::CACHE_GROUP );

        return $results;
    }

    /**
     * Get packages for a specific treatment by user
     *
     * @param int    $user_id      User ID.
     * @param int    $treatment_id Treatment post ID.
     * @param string $status       Optional. Filter by status.
     * @return array
     */
    public static function get_by_user_and_treatment( $user_id, $treatment_id, $status = '' ) {
        global $wpdb;

        $cache_key = 'get_by_user_treatment_' . $user_id . '_' . $treatment_id . '_' . $status;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached ) {
            return $cached;
        }

        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a safe prefixed table name.
        $sql = "SELECT * FROM {$table} WHERE user_id = %d AND treatment_id = %d";
        $params = array( $user_id, $treatment_id );

        if ( ! empty( $status ) ) {
            $sql .= ' AND status = %s';
            $params[] = $status;
        }

        $sql .= ' ORDER BY created_at DESC';

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query.
        $results = $wpdb->get_results(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is built safely with placeholders above.
            $wpdb->prepare( $sql, $params )
        );

        wp_cache_set( $cache_key, $results, self::CACHE_GROUP );

        return $results;
    }

    /**
     * Create a new customer package record
     *
     * @param array $data Package data.
     * @return int|false Insert ID or false on failure.
     */
    public static function create( array $data ) {
        global $wpdb;

        $table = self::get_table_name();

        $defaults = array(
            'user_id'            => 0,
            'order_id'           => 0,
            'order_item_id'      => 0,
            'treatment_id'       => 0,
            'package_id'         => 0,
            'package_name'       => '',
            'sessions_purchased' => 1,
            'sessions_remaining' => 1,
            'total_price'        => 0.00,
            'deposit_paid'       => 0.00,
            'remaining_balance'  => 0.00,
            'status'             => self::STATUS_ACTIVE,
            'notes'              => '',
            'created_at'         => current_time( 'mysql' ),
            'updated_at'         => current_time( 'mysql' ),
        );

        $data = wp_parse_args( $data, $defaults );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table insert.
        $result = $wpdb->insert(
            $table,
            array(
                'user_id'            => $data['user_id'],
                'order_id'           => $data['order_id'],
                'order_item_id'      => $data['order_item_id'],
                'treatment_id'       => $data['treatment_id'],
                'package_id'         => $data['package_id'],
                'package_name'       => $data['package_name'],
                'sessions_purchased' => $data['sessions_purchased'],
                'sessions_remaining' => $data['sessions_remaining'],
                'total_price'        => $data['total_price'],
                'deposit_paid'       => $data['deposit_paid'],
                'remaining_balance'  => $data['remaining_balance'],
                'status'             => $data['status'],
                'notes'              => $data['notes'],
                'created_at'         => $data['created_at'],
                'updated_at'         => $data['updated_at'],
            ),
            array(
                '%d', '%d', '%d', '%d', '%d', '%s', '%d', '%d',
                '%f', '%f', '%f', '%s', '%s', '%s', '%s',
            )
        );

        if ( false === $result ) {
            return false;
        }

        $insert_id = $wpdb->insert_id;

        self::invalidate_cache( $data['user_id'] );

        /**
         * Action fired after a customer package is created
         *
         * @param int   $insert_id Customer package ID.
         * @param array $data      Package data.
         */
        do_action( 'tpd_customer_package_created', $insert_id, $data );

        return $insert_id;
    }

    /**
     * Update a customer package
     *
     * @param int   $id   Customer package ID.
     * @param array $data Data to update.
     * @return bool
     */
    public static function update( $id, array $data ) {
        global $wpdb;

        $table = self::get_table_name();

        // Always update timestamp
        $data['updated_at'] = current_time( 'mysql' );

        // Build format array
        $format = array();
        foreach ( $data as $key => $value ) {
            if ( in_array( $key, array( 'total_price', 'deposit_paid', 'remaining_balance' ), true ) ) {
                $format[] = '%f';
            } elseif ( in_array( $key, array( 'user_id', 'order_id', 'order_item_id', 'treatment_id', 'package_id', 'sessions_purchased', 'sessions_remaining' ), true ) ) {
                $format[] = '%d';
            } else {
                $format[] = '%s';
            }
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table update; cache invalidated below.
        $result = $wpdb->update(
            $table,
            $data,
            array( 'id' => $id ),
            $format,
            array( '%d' )
        );

        if ( false !== $result ) {
            self::invalidate_cache();

            /**
             * Action fired after a customer package is updated
             *
             * @param int   $id   Customer package ID.
             * @param array $data Updated data.
             */
            do_action( 'tpd_customer_package_updated', $id, $data );
        }

        return false !== $result;
    }

    /**
     * Use a session from a customer package
     *
     * @param int    $id    Customer package ID.
     * @param string $notes Optional. Notes about the session.
     * @return bool|array False on failure, or array with remaining sessions.
     */
    public static function use_session( $id, $notes = '' ) {
        $package = self::find( $id );

        if ( ! $package ) {
            return false;
        }

        if ( $package->sessions_remaining <= 0 ) {
            return false;
        }

        $new_remaining = $package->sessions_remaining - 1;
        $new_status = $new_remaining <= 0 ? self::STATUS_COMPLETED : $package->status;

        // Append to notes
        $note_entry = sprintf(
            '[%s] Session used. Remaining: %d',
            current_time( 'Y-m-d H:i:s' ),
            $new_remaining
        );

        if ( ! empty( $notes ) ) {
            $note_entry .= ' - ' . $notes;
        }

        $existing_notes = $package->notes ? $package->notes . "\n" : '';

        $result = self::update( $id, array(
            'sessions_remaining' => $new_remaining,
            'status'             => $new_status,
            'notes'              => $existing_notes . $note_entry,
        ) );

        if ( ! $result ) {
            return false;
        }

        /**
         * Action fired after a session is used
         *
         * @param int    $id            Customer package ID.
         * @param int    $new_remaining Remaining sessions.
         * @param object $package       Package data before update.
         */
        do_action( 'tpd_session_used', $id, $new_remaining, $package );

        return array(
            'sessions_remaining' => $new_remaining,
            'status'             => $new_status,
        );
    }

    /**
     * Update remaining balance (e.g., when customer pays more)
     *
     * @param int   $id             Customer package ID.
     * @param float $payment_amount Amount paid.
     * @return bool|float False on failure, or new remaining balance.
     */
    public static function record_payment( $id, $payment_amount ) {
        $package = self::find( $id );

        if ( ! $package ) {
            return false;
        }

        $new_balance = max( 0, $package->remaining_balance - $payment_amount );
        $new_deposit_paid = $package->deposit_paid + $payment_amount;

        // Add note
        $note_entry = sprintf(
            '[%s] Payment received: %s. New balance: %s',
            current_time( 'Y-m-d H:i:s' ),
            wc_price( $payment_amount ),
            wc_price( $new_balance )
        );

        $existing_notes = $package->notes ? $package->notes . "\n" : '';

        $result = self::update( $id, array(
            'deposit_paid'      => $new_deposit_paid,
            'remaining_balance' => $new_balance,
            'notes'             => $existing_notes . $note_entry,
        ) );

        if ( ! $result ) {
            return false;
        }

        /**
         * Action fired after a payment is recorded
         *
         * @param int    $id             Customer package ID.
         * @param float  $payment_amount Payment amount.
         * @param float  $new_balance    New remaining balance.
         * @param object $package        Package data before update.
         */
        do_action( 'tpd_payment_recorded', $id, $payment_amount, $new_balance, $package );

        return $new_balance;
    }

    /**
     * Cancel a customer package
     *
     * @param int    $id     Customer package ID.
     * @param string $reason Optional. Cancellation reason.
     * @return bool
     */
    public static function cancel( $id, $reason = '' ) {
        $package = self::find( $id );

        if ( ! $package ) {
            return false;
        }

        $note_entry = sprintf(
            '[%s] Package cancelled.',
            current_time( 'Y-m-d H:i:s' )
        );

        if ( ! empty( $reason ) ) {
            $note_entry .= ' Reason: ' . $reason;
        }

        $existing_notes = $package->notes ? $package->notes . "\n" : '';

        return self::update( $id, array(
            'status' => self::STATUS_CANCELLED,
            'notes'  => $existing_notes . $note_entry,
        ) );
    }

    /**
     * Count customer packages by status
     *
     * @param int    $user_id User ID. Optional, 0 for all users.
     * @param string $status  Status to count. Optional.
     * @return int
     */
    public static function count( $user_id = 0, $status = '' ) {
        global $wpdb;

        $cache_key = 'count_' . $user_id . '_' . $status;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached ) {
            return $cached;
        }

        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a safe prefixed table name.
        $sql = "SELECT COUNT(*) FROM {$table} WHERE 1=1";
        $params = array();

        if ( $user_id > 0 ) {
            $sql .= ' AND user_id = %d';
            $params[] = $user_id;
        }

        if ( ! empty( $status ) ) {
            $sql .= ' AND status = %s';
            $params[] = $status;
        }

        if ( ! empty( $params ) ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query.
            $result = (int) $wpdb->get_var(
                // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is built safely with placeholders above.
                $wpdb->prepare( $sql, $params )
            );
        } else {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.PreparedSQL.NotPrepared -- No parameters needed for unfiltered count.
            $result = (int) $wpdb->get_var( $sql );
        }

        wp_cache_set( $cache_key, $result, self::CACHE_GROUP );

        return $result;
    }

    /**
     * Get total sessions remaining for a user
     *
     * @param int $user_id      User ID.
     * @param int $treatment_id Optional. Filter by treatment.
     * @return int
     */
    public static function get_total_sessions_remaining( $user_id, $treatment_id = 0 ) {
        global $wpdb;

        $cache_key = 'sessions_remaining_' . $user_id . '_' . $treatment_id;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached ) {
            return $cached;
        }

        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a safe prefixed table name.
        $sql = "SELECT SUM(sessions_remaining) FROM {$table} WHERE user_id = %d AND status = %s";
        $params = array( $user_id, self::STATUS_ACTIVE );

        if ( $treatment_id > 0 ) {
            $sql .= ' AND treatment_id = %d';
            $params[] = $treatment_id;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query.
        $result = $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is built safely with placeholders above.
            $wpdb->prepare( $sql, $params )
        );

        $value = $result ? (int) $result : 0;

        wp_cache_set( $cache_key, $value, self::CACHE_GROUP );

        return $value;
    }

    /**
     * Get total remaining balance for a user
     *
     * @param int $user_id User ID.
     * @return float
     */
    public static function get_total_remaining_balance( $user_id ) {
        global $wpdb;

        $cache_key = 'remaining_balance_' . $user_id;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached ) {
            return $cached;
        }

        $table = self::get_table_name();

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table query.
        $result = $wpdb->get_var(
            // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- $table is a safe prefixed table name.
            $wpdb->prepare(
                "SELECT SUM(remaining_balance) FROM {$table} WHERE user_id = %d AND status = %s",
                $user_id,
                self::STATUS_ACTIVE
            )
        );

        $value = $result ? (float) $result : 0.00;

        wp_cache_set( $cache_key, $value, self::CACHE_GROUP );

        return $value;
    }

    /**
     * Delete a customer package (use with caution)
     *
     * @param int $id Customer package ID.
     * @return bool
     */
    public static function delete( $id ) {
        global $wpdb;

        $table = self::get_table_name();

        $package = self::find( $id );

        if ( ! $package ) {
            return false;
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Custom table delete; cache invalidated below.
        $result = $wpdb->delete(
            $table,
            array( 'id' => $id ),
            array( '%d' )
        );

        if ( false !== $result ) {
            self::invalidate_cache( $package->user_id );

            /**
             * Action fired after a customer package is deleted
             *
             * @param int    $id      Customer package ID.
             * @param object $package Package data before deletion.
             */
            do_action( 'tpd_customer_package_deleted', $id, $package );
        }

        return false !== $result;
    }
}
