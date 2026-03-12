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
     * Get the full table name with prefix
     *
     * @return string
     */
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . self::TABLE_NAME;
    }

    /**
     * Find a package by ID
     *
     * @param int $id Package ID.
     * @return PackageModel|null
     */
    public static function find( $id ) {
        global $wpdb;

        $table = self::get_table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE id = %d",
                $id
            )
        );

        if ( ! $row ) {
            return null;
        }

        return new PackageModel( $row );
    }

    /**
     * Find a package by WooCommerce product ID
     *
     * @param int $product_id WooCommerce product ID.
     * @return PackageModel|null
     */
    public static function find_by_product_id( $product_id ) {
        global $wpdb;

        $table = self::get_table_name();
        $row = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE wc_product_id = %d",
                $product_id
            )
        );

        if ( ! $row ) {
            return null;
        }

        return new PackageModel( $row );
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
        $table = self::get_table_name();

        // Sanitize orderby
        $allowed_orderby = array( 'sort_order', 'sessions', 'total_price', 'name', 'id', 'created_at' );
        $orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'sort_order';

        // Sanitize order
        $order = 'DESC' === strtoupper( $args['order'] ) ? 'DESC' : 'ASC';

        $rows = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE treatment_id = %d ORDER BY {$orderby} {$order}",
                $treatment_id
            )
        );

        $packages = array();
        foreach ( $rows as $row ) {
            $packages[] = new PackageModel( $row );
        }

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

        $sql = "SELECT * FROM {$table} ORDER BY {$orderby} {$order}";

        if ( $args['limit'] > 0 ) {
            $sql .= $wpdb->prepare( ' LIMIT %d OFFSET %d', $args['limit'], $args['offset'] );
        }

        $rows = $wpdb->get_results( $sql );

        $packages = array();
        foreach ( $rows as $row ) {
            $packages[] = new PackageModel( $row );
        }

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

            $result = $wpdb->insert( $table, $data, $format );

            if ( false === $result ) {
                return false;
            }

            $package->set_id( $wpdb->insert_id );
        }

        // Trigger action for WooCommerce sync
        do_action( 'tp_package_saved', $package );

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

        // Trigger action before deletion (for WooCommerce product cleanup)
        do_action( 'tp_package_before_delete', $package );

        $result = $wpdb->delete(
            $table,
            array( 'id' => $id ),
            array( '%d' )
        );

        if ( false === $result ) {
            return false;
        }

        // Trigger action after deletion
        do_action( 'tp_package_deleted', $id, $package );

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
            do_action( 'tp_package_before_delete', $package );
        }

        $result = $wpdb->delete(
            $table,
            array( 'treatment_id' => $treatment_id ),
            array( '%d' )
        );

        if ( $result > 0 ) {
            foreach ( $packages as $package ) {
                do_action( 'tp_package_deleted', $package->get_id(), $package );
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

        $table = self::get_table_name();

        return (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM {$table} WHERE treatment_id = %d",
                $treatment_id
            )
        );
    }

    /**
     * Get the minimum per-session price for a treatment
     *
     * @param int $treatment_id Treatment post ID.
     * @return float|null
     */
    public static function get_min_per_session_price( $treatment_id ) {
        global $wpdb;

        $table = self::get_table_name();

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MIN(per_session_price) FROM {$table} WHERE treatment_id = %d",
                $treatment_id
            )
        );

        return $result !== null ? (float) $result : null;
    }

    /**
     * Get the maximum discount percentage for a treatment
     *
     * @param int $treatment_id Treatment post ID.
     * @return float|null
     */
    public static function get_max_discount( $treatment_id ) {
        global $wpdb;

        $table = self::get_table_name();

        $result = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT MAX(discount_percent) FROM {$table} WHERE treatment_id = %d",
                $treatment_id
            )
        );

        return $result !== null ? (float) $result : null;
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
        }

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

        $args = wp_parse_args( $args, $defaults );
        $table = self::get_table_name();

        $sql = "SELECT * FROM {$table} WHERE name LIKE %s";
        $params = array( '%' . $wpdb->esc_like( $search ) . '%' );

        if ( $args['treatment_id'] > 0 ) {
            $sql .= ' AND treatment_id = %d';
            $params[] = $args['treatment_id'];
        }

        $sql .= ' ORDER BY name ASC';

        if ( $args['limit'] > 0 ) {
            $sql .= ' LIMIT %d';
            $params[] = $args['limit'];
        }

        $rows = $wpdb->get_results(
            $wpdb->prepare( $sql, $params )
        );

        $packages = array();
        foreach ( $rows as $row ) {
            $packages[] = new PackageModel( $row );
        }

        return $packages;
    }
}
