<?php
/**
 * Database Installer
 *
 * @package TreatmentPackages\DB
 */

namespace TreatmentPackages\DB;

defined( 'ABSPATH' ) || exit;

/**
 * Installer Class
 *
 * Handles database table creation and updates.
 */
class Installer {

    /**
     * Database version
     *
     * @var string
     */
    const DB_VERSION = '1.0.0';

    /**
     * Install database tables
     */
    public static function install() {
        self::create_tables();
        self::update_db_version();
    }

    /**
     * Create custom database tables
     */
    private static function create_tables() {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        // Packages table
        $packages_table = $wpdb->prefix . 'tp_packages';
        $packages_sql = "CREATE TABLE {$packages_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            treatment_id BIGINT(20) UNSIGNED NOT NULL,
            name VARCHAR(255) NOT NULL,
            sessions INT(11) NOT NULL DEFAULT 1,
            total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            per_session_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            discount_percent DECIMAL(5,2) NOT NULL DEFAULT 0.00,
            deposit_type VARCHAR(20) NOT NULL DEFAULT 'none',
            deposit_value DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            wc_product_id BIGINT(20) UNSIGNED DEFAULT NULL,
            sort_order INT(11) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY treatment_id (treatment_id),
            KEY wc_product_id (wc_product_id)
        ) {$charset_collate};";

        dbDelta( $packages_sql );

        // Customer packages table (purchased sessions tracking)
        $customer_packages_table = $wpdb->prefix . 'tp_customer_packages';
        $customer_packages_sql = "CREATE TABLE {$customer_packages_table} (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            user_id BIGINT(20) UNSIGNED NOT NULL,
            order_id BIGINT(20) UNSIGNED NOT NULL,
            order_item_id BIGINT(20) UNSIGNED NOT NULL,
            treatment_id BIGINT(20) UNSIGNED NOT NULL,
            package_id BIGINT(20) UNSIGNED NOT NULL,
            package_name VARCHAR(255) NOT NULL,
            sessions_purchased INT(11) NOT NULL DEFAULT 1,
            sessions_remaining INT(11) NOT NULL DEFAULT 1,
            total_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            deposit_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            remaining_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            notes TEXT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY order_id (order_id),
            KEY treatment_id (treatment_id),
            KEY package_id (package_id),
            KEY status (status)
        ) {$charset_collate};";

        dbDelta( $customer_packages_sql );
    }

    /**
     * Update the database version option
     */
    private static function update_db_version() {
        update_option( 'tp_deposits_db_version', self::DB_VERSION );
    }

    /**
     * Get current database version
     *
     * @return string
     */
    public static function get_db_version() {
        return get_option( 'tp_deposits_db_version', '0' );
    }

    /**
     * Check if database needs update
     *
     * @return bool
     */
    public static function needs_update() {
        return version_compare( self::get_db_version(), self::DB_VERSION, '<' );
    }
}
