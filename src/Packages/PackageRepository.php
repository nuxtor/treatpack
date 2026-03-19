<?php
/**
 * Package Repository
 *
 * @package TreatmentPackages\Packages
 */

namespace TreatmentPackages\Packages;

defined( 'ABSPATH' ) || exit;

/**
 * PackageRepository Class
 *
 * Handles CRUD operations for treatment packages in the database.
 */
class PackageRepository {

    /**
     * Table name (without prefix)
     *
     * @var string
     */
    const TABLE_NAME = 'tp_packages';

    /**
     * Cache group name
     *
     * @var string
     */
    const CACHE_GROUP = 'tpd_packages';

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
     * Invalidate all caches related to a treatment or package.
     *
     * @param int $package_id   Package ID.
     * @param int $treatment_id Treatment post ID.
     * @param int $product_id   WooCommerce product ID.
     */
    private static function invalidate_cache( $package_id = 0, $treatment_id = 0, $product_id = 0 ) {
        if ( $package_id ) {
            wp_cache_delete( 'pkg_' . $package_id, self::CACHE_GROUP );
        }
        if ( $treatment_id ) {
            wp_cache_delete( 'treatment_' . $treatment_id, self::CACHE_GROUP );
            wp_cache_delete( 'count_treatment_' . $treatment_id, self::CACHE_GROUP );
            wp_cache_delete( 'min_price_treatment_' . $treatment_id, self::CACHE_GROUP );
            wp_cache_delete( 'max_discount_treatment_' . $treatment_id, self::CACHE_GROUP );
        }
        if ( $product_id ) {
            wp_cache_delete( 'product_' . $product_id, self::CACHE_GROUP );
        }
        wp_cache_delete( 'all_packages', self::CACHE_GROUP );
        wp_cache_delete( 'search_packages', self::CACHE_GROUP );
    }

    /**
     * Find a package by ID
     *
     * @param int $id Package ID.
     * @return PackageModel|null
     */
    public static function find( $id ) {
        global $wpdb;

        $cache_key = 'pkg_' . $id;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached ) {
            return $cached;
        }

        $table = self::get_table_name();
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe.
                "SELECT * FROM {$table} WHERE id = %d",
                $id
            )
        );

        if ( ! $row ) {
            wp_cache_set( $cache_key, null, self::CACHE_GROUP, HOUR_IN_SECONDS );
            return null;
        }

        $package = new PackageModel( $row );
        wp_cache_set( $cache_key, $package, self::CACHE_GROUP, HOUR_IN_SECONDS );

        return $package;
    }

    /**
     * Find a package by WooCommerce product ID
     *
     * @param int $product_id WooCommerce product ID.
     * @return PackageModel|null
     */
    public static function find_by_product_id( $product_id ) {
        global $wpdb;

        $cache_key = 'product_' . $product_id;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached ) {
            return $cached;
        }

        $table = self::get_table_name();
        $row   = $wpdb->get_row(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe.
                "SELECT * FROM {$table} WHERE wc_product_id = %d",
                $product_id
            )
        );

        if ( ! $row ) {
            wp_cache_set( $cache_key, null, self::CACHE_GROUP, HOUR_IN_SECONDS );
            return null;
        }

        $package = new PackageModel( $row );
        wp_cache_set( $cache_key, $package, self::CACHE_GROUP, HOUR_IN_SECONDS );

        return $package;
    }

    /**
     * Get all packages for a treatment
     *
     * @param int   $treatment_id Treatment post ID.
     * @param array $args         Optional. Query arguments.
     * @return PackageModel[]
     */
    public static function get_by_treatment( $treatment_id, $args = array() ) {
        global $wpdb;

        $defaults = array(
            'orderby' => 'sort_order',
            'order'   => 'ASC',
        );

        $args = wp_parse_args( $args, $defaults );

        // Sanitize orderby
        $allowed_orderby = array( 'sort_order', 'sessions', 'total_price', 'name', 'id', 'created_at' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'sort_order';

        // Sanitize order
        $order = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

        $cache_key = 'treatment_' . $treatment_id . '_' . $orderby . '_' . $order;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached ) {
            return $cached;
        }

        $table = self::get_table_name();

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and sanitized orderby/order are safe.
                "SELECT * FROM {$table} WHERE treatment_id = %d ORDER BY {$orderby} {$order}",
                $treatment_id
            )
        );

        $packages = array();
        foreach ( $rows as $row ) {
            $packages[] = new PackageModel( $row );
        }

        wp_cache_set( $cache_key, $packages, self::CACHE_GROUP, HOUR_IN_SECONDS );

        return $packages;
    }

    /**
     * Get all packages
     *
     * @param array $args Optional. Query arguments.
     * @return PackageModel[]
     */
    public static function get_all( $args = array() ) {
        global $wpdb;

        $defaults = array(
            'orderby' => 'treatment_id',
            'order'   => 'ASC',
            'limit'   => -1,
            'offset'  => 0,
        );

        $args = wp_parse_args( $args, $defaults );
        $table = self::get_table_name();

        // Sanitize orderby
        $allowed_orderby = array( 'id', 'treatment_id', 'sort_order', 'sessions', 'total_price', 'name', 'created_at' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'treatment_id';

        // Sanitize order
        $order = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

        $cache_key = 'all_' . $orderby . '_' . $order . '_' . intval( $args['limit'] ) . '_' . intval( $args['offset'] );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached ) {
            return $cached;
        }

        if ( $args['limit'] > 0 ) {
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and sanitized orderby/order are safe.
                    "SELECT * FROM {$table} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d",
                    $args['limit'],
                    $args['offset']
                )
            );
        } else {
            $rows = $wpdb->get_results(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name and sanitized orderby/order are safe.
                "SELECT * FROM {$table} ORDER BY {$orderby} {$order}"
            );
        }

        $packages = array();
        foreach ( $rows as $row ) {
            $packages[] = new PackageModel( $row );
        }

        wp_cache_set( $cache_key, $packages, self::CACHE_GROUP, HOUR_IN_SECONDS );

        return $packages;
    }

    /**
     * Save a package (insert or update)
     *
     * @param PackageModel $package Package model to save.
     * @return PackageModel|false The saved package with ID, or false on failure.
     */
    public static function save( PackageModel $package ) {
        global $wpdb;

        $table = self::get_table_name();

        // Get base price for discount calculation
        $base_price = get_post_meta( $package->get_treatment_id(), '_tp_base_price', true );
        $package->calculate_prices( $base_price ? (float) $base_price : null );

        $data = array(
            'treatment_id'      => $package->get_treatment_id(),
            'name'              => $package->get_name(),
            'sessions'          => $package->get_sessions(),
            'total_price'       => $package->get_total_price(),
            'per_session_price' => $package->get_per_session_price(),
            'discount_percent'  => $package->get_discount_percent(),
            'deposit_type'      => $package->get_deposit_type(),
            'deposit_value'     => $package->get_deposit_value(),
            'wc_product_id'     => $package->get_wc_product_id(),
            'sort_order'        => $package->get_sort_order(),
        );

        $format = array(
            '%d', // treatment_id
            '%s', // name
            '%d', // sessions
            '%f', // total_price
            '%f', // per_session_price
            '%f', // discount_percent
            '%s', // deposit_type
            '%f', // deposit_value
            '%d', // wc_product_id
            '%d', // sort_order
        );

        if ( $package->get_id() > 0 ) {
            // Update existing
            $data['updated_at'] = current_time( 'mysql' );
            $format[] = '%s';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- write operation, cache invalidated below.
            $result = $wpdb->update(
                $table,
                $data,
                array( 'id' => $package->get_id() ),
                $format,
                array( '%d' )
            );

            if ( false === $result ) {
                return false;
            }
        } else {
            // Insert new
            $data['created_at'] = current_time( 'mysql' );
            $data['updated_at'] = current_time( 'mysql' );
            $format[] = '%s';
            $format[] = '%s';

            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- write operation, cache invalidated below.
            $result = $wpdb->insert( $table, $data, $format );

            if ( false === $result ) {
                return false;
            }

            $package->set_id( $wpdb->insert_id );
        }

        // Invalidate caches.
        self::invalidate_cache( $package->get_id(), $package->get_treatment_id(), $package->get_wc_product_id() );

        // Trigger action for WooCommerce sync.
        do_action( 'tpd_package_saved', $package );

        return $package;
    }

    /**
     * Delete a package
     *
     * @param int $id Package ID.
     * @return bool True on success, false on failure.
     */
    public static function delete( $id ) {
        global $wpdb;

        $table = self::get_table_name();

        // Get package before deletion for cleanup
        $package = self::find( $id );

        if ( ! $package ) {
            return false;
        }

        // Trigger action before deletion (for WooCommerce product cleanup).
        do_action( 'tpd_package_before_delete', $package );

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- write operation, cache invalidated below.
        $result = $wpdb->delete(
            $table,
            array( 'id' => $id ),
            array( '%d' )
        );

        if ( false === $result ) {
            return false;
        }

        // Invalidate caches.
        self::invalidate_cache( $id, $package->get_treatment_id(), $package->get_wc_product_id() );

        // Trigger action after deletion.
        do_action( 'tpd_package_deleted', $id, $package );

        return true;
    }

    /**
     * Delete all packages for a treatment
     *
     * @param int $treatment_id Treatment post ID.
     * @return int Number of deleted packages.
     */
    public static function delete_by_treatment( $treatment_id ) {
        global $wpdb;

        $table = self::get_table_name();

        // Get packages before deletion for cleanup
        $packages = self::get_by_treatment( $treatment_id );

        foreach ( $packages as $package ) {
            do_action( 'tpd_package_before_delete', $package );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- write operation, cache invalidated below.
        $result = $wpdb->delete(
            $table,
            array( 'treatment_id' => $treatment_id ),
            array( '%d' )
        );

        if ( $result > 0 ) {
            foreach ( $packages as $package ) {
                self::invalidate_cache( $package->get_id(), $treatment_id, $package->get_wc_product_id() );
                do_action( 'tpd_package_deleted', $package->get_id(), $package );
            }
        }

        return $result ? $result : 0;
    }

    /**
     * Count packages for a treatment
     *
     * @param int $treatment_id Treatment post ID.
     * @return int
     */
    public static function count_by_treatment( $treatment_id ) {
        global $wpdb;

        $cache_key = 'count_treatment_' . $treatment_id;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached ) {
            return (int) $cached;
        }

        $table = self::get_table_name();

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe.
                "SELECT COUNT(*) FROM {$table} WHERE treatment_id = %d",
                $treatment_id
            )
        );

        wp_cache_set( $cache_key, $count, self::CACHE_GROUP, HOUR_IN_SECONDS );

        return $count;
    }

    /**
     * Get the minimum per-session price for a treatment
     *
     * @param int $treatment_id Treatment post ID.
     * @return float|null
     */
    public static function get_min_per_session_price( $treatment_id ) {
        global $wpdb;

        $cache_key = 'min_price_treatment_' . $treatment_id;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached ) {
            return $cached;
        }

        $table = self::get_table_name();

        $result = $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe.
                "SELECT MIN(per_session_price) FROM {$table} WHERE treatment_id = %d",
                $treatment_id
            )
        );

        $value = $result !== null ? (float) $result : null;
        wp_cache_set( $cache_key, $value, self::CACHE_GROUP, HOUR_IN_SECONDS );

        return $value;
    }

    /**
     * Get the maximum discount percentage for a treatment
     *
     * @param int $treatment_id Treatment post ID.
     * @return float|null
     */
    public static function get_max_discount( $treatment_id ) {
        global $wpdb;

        $cache_key = 'max_discount_treatment_' . $treatment_id;
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached ) {
            return $cached;
        }

        $table = self::get_table_name();

        $result = $wpdb->get_var(
            $wpdb->prepare(
                // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe.
                "SELECT MAX(discount_percent) FROM {$table} WHERE treatment_id = %d",
                $treatment_id
            )
        );

        $value = $result !== null ? (float) $result : null;
        wp_cache_set( $cache_key, $value, self::CACHE_GROUP, HOUR_IN_SECONDS );

        return $value;
    }

    /**
     * Bulk update sort order for packages
     *
     * @param array $order_map Array of package_id => sort_order.
     * @return bool
     */
    public static function update_sort_order( array $order_map ) {
        global $wpdb;

        $table = self::get_table_name();

        foreach ( $order_map as $package_id => $sort_order ) {
            // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- write operation, cache invalidated below.
            $wpdb->update(
                $table,
                array(
                    'sort_order'  => (int) $sort_order,
                    'updated_at'  => current_time( 'mysql' ),
                ),
                array( 'id' => (int) $package_id ),
                array( '%d', '%s' ),
                array( '%d' )
            );

            wp_cache_delete( 'pkg_' . (int) $package_id, self::CACHE_GROUP );
        }

        // Invalidate list caches.
        wp_cache_delete( 'all_packages', self::CACHE_GROUP );
        wp_cache_delete( 'search_packages', self::CACHE_GROUP );

        return true;
    }

    /**
     * Check if a treatment has any packages
     *
     * @param int $treatment_id Treatment post ID.
     * @return bool
     */
    public static function treatment_has_packages( $treatment_id ) {
        return self::count_by_treatment( $treatment_id ) > 0;
    }

    /**
     * Search packages by name
     *
     * @param string $search Search term.
     * @param array  $args   Optional. Query arguments.
     * @return PackageModel[]
     */
    public static function search( $search, $args = array() ) {
        global $wpdb;

        $defaults = array(
            'treatment_id' => 0,
            'limit'        => 20,
        );

        $args  = wp_parse_args( $args, $defaults );
        $table = self::get_table_name();

        $cache_key = 'search_' . md5( $search . wp_json_encode( $args ) );
        $cached    = wp_cache_get( $cache_key, self::CACHE_GROUP );

        if ( false !== $cached ) {
            return $cached;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe.
        $sql    = "SELECT * FROM {$table} WHERE name LIKE %s";
        $params = array( '%' . $wpdb->esc_like( $search ) . '%' );

        if ( $args['treatment_id'] > 0 ) {
            $sql     .= ' AND treatment_id = %d';
            $params[] = $args['treatment_id'];
        }

        $sql .= ' ORDER BY name ASC';

        if ( $args['limit'] > 0 ) {
            $sql     .= ' LIMIT %d';
            $params[] = $args['limit'];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare( $sql, $params ) // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is built with safe table name and all variables use placeholders.
        );

        $packages = array();
        foreach ( $rows as $row ) {
            $packages[] = new PackageModel( $row );
        }

        wp_cache_set( $cache_key, $packages, self::CACHE_GROUP, HOUR_IN_SECONDS );

        return $packages;
    }
}
