<?php

namespace TreatmentPackages\DB;

defined('ABSPATH') || exit;

/**
 * Database Installer
 *
 * Creates and manages custom database tables
 */
class Installer {

    /**
     * Database version
     */
    const DB_VERSION = '1.0.0';

    /**
     * Install database tables
     */
    public static function install(): void {
        self::create_tables();
        self::update_db_version();
    }

    /**
     * Create custom tables
     */
    private static function create_tables(): void {
        global $wpdb;

        $charset_collate = $wpdb->get_charset_collate();

        $sql = [];

        // Packages table - stores treatment package definitions
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tp_packages (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            treatment_id BIGINT(20) UNSIGNED NOT NULL,
            product_id BIGINT(20) UNSIGNED DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            sessions INT(11) UNSIGNED NOT NULL DEFAULT 1,
            price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            sale_price DECIMAL(10,2) DEFAULT NULL,
            deposit_type ENUM('none', 'fixed', 'percentage', 'pay_at_location') NOT NULL DEFAULT 'none',
            deposit_amount DECIMAL(10,2) DEFAULT NULL,
            description TEXT DEFAULT NULL,
            sort_order INT(11) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY treatment_id (treatment_id),
            KEY product_id (product_id),
            KEY is_active (is_active)
        ) $charset_collate;";

        // Customer packages table - tracks purchased packages and sessions
        $sql[] = "CREATE TABLE IF NOT EXISTS {$wpdb->prefix}tp_customer_packages (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            customer_id BIGINT(20) UNSIGNED NOT NULL,
            order_id BIGINT(20) UNSIGNED NOT NULL,
            order_item_id BIGINT(20) UNSIGNED NOT NULL,
            package_id BIGINT(20) UNSIGNED NOT NULL,
            treatment_id BIGINT(20) UNSIGNED NOT NULL,
            package_name VARCHAR(255) NOT NULL,
            treatment_name VARCHAR(255) NOT NULL,
            total_sessions INT(11) UNSIGNED NOT NULL,
            sessions_used INT(11) UNSIGNED NOT NULL DEFAULT 0,
            total_price DECIMAL(10,2) NOT NULL,
            deposit_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            balance_due DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            balance_paid DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            status ENUM('active', 'completed', 'cancelled', 'expired') NOT NULL DEFAULT 'active',
            notes TEXT DEFAULT NULL,
            expires_at DATETIME DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY customer_id (customer_id),
            KEY order_id (order_id),
            KEY package_id (package_id),
            KEY treatment_id (treatment_id),
            KEY status (status)
        ) $charset_collate;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        foreach ($sql as $query) {
            dbDelta($query);
        }
    }

    /**
     * Update database version option
     */
    private static function update_db_version(): void {
        update_option('treatpack_db_version', self::DB_VERSION);
    }

    /**
     * Get packages table name
     *
     * @return string
     */
    public static function get_packages_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'tp_packages';
    }

    /**
     * Get customer packages table name
     *
     * @return string
     */
    public static function get_customer_packages_table(): string {
        global $wpdb;
        return $wpdb->prefix . 'tp_customer_packages';
    }

    /**
     * Check if tables exist
     *
     * @return bool
     */
    public static function tables_exist(): bool {
        global $wpdb;

        $packages_table = self::get_packages_table();
        $customer_packages_table = self::get_customer_packages_table();

        $packages_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $packages_table)
        ) === $packages_table;

        $customer_packages_exists = $wpdb->get_var(
            $wpdb->prepare("SHOW TABLES LIKE %s", $customer_packages_table)
        ) === $customer_packages_table;

        return $packages_exists && $customer_packages_exists;
    }

    /**
     * Drop tables (for uninstall)
     */
    public static function drop_tables(): void {
        global $wpdb;

        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tp_customer_packages");
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}tp_packages");

        delete_option('treatpack_db_version');
    }
}
