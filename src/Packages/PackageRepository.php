<?php

namespace TreatmentPackages\Packages;

use TreatmentPackages\DB\Installer;

defined('ABSPATH') || exit;

/**
 * Package Repository
 *
 * CRUD operations for packages
 */
class PackageRepository {

    /**
     * Get table name
     *
     * @return string
     */
    private static function table(): string {
        return Installer::get_packages_table();
    }

    /**
     * Find package by ID
     *
     * @param int $id Package ID
     * @return PackageModel|null
     */
    public static function find(int $id): ?PackageModel {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE id = %d",
            $id
        ));

        return $row ? PackageModel::from_row($row) : null;
    }

    /**
     * Find package by product ID
     *
     * @param int $product_id WooCommerce product ID
     * @return PackageModel|null
     */
    public static function find_by_product(int $product_id): ?PackageModel {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE product_id = %d",
            $product_id
        ));

        return $row ? PackageModel::from_row($row) : null;
    }

    /**
     * Get all packages for a treatment
     *
     * @param int  $treatment_id Treatment post ID
     * @param bool $active_only  Only return active packages
     * @return PackageModel[]
     */
    public static function get_by_treatment(int $treatment_id, bool $active_only = true): array {
        global $wpdb;

        $sql = "SELECT * FROM " . self::table() . " WHERE treatment_id = %d";

        if ($active_only) {
            $sql .= " AND is_active = 1";
        }

        $sql .= " ORDER BY sort_order ASC, id ASC";

        $rows = $wpdb->get_results($wpdb->prepare($sql, $treatment_id));

        return array_map([PackageModel::class, 'from_row'], $rows);
    }

    /**
     * Get all packages
     *
     * @param array $args Query arguments
     * @return PackageModel[]
     */
    public static function get_all(array $args = []): array {
        global $wpdb;

        $defaults = [
            'active_only' => true,
            'treatment_id' => null,
            'limit' => null,
            'offset' => 0,
            'orderby' => 'sort_order',
            'order' => 'ASC',
        ];

        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT * FROM " . self::table() . " WHERE 1=1";
        $params = [];

        if ($args['active_only']) {
            $sql .= " AND is_active = 1";
        }

        if ($args['treatment_id']) {
            $sql .= " AND treatment_id = %d";
            $params[] = $args['treatment_id'];
        }

        $allowed_orderby = ['id', 'name', 'price', 'sessions', 'sort_order', 'created_at'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'sort_order';
        $order = strtoupper($args['order']) === 'DESC' ? 'DESC' : 'ASC';

        $sql .= " ORDER BY {$orderby} {$order}";

        if ($args['limit']) {
            $sql .= " LIMIT %d OFFSET %d";
            $params[] = $args['limit'];
            $params[] = $args['offset'];
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        $rows = $wpdb->get_results($sql);

        return array_map([PackageModel::class, 'from_row'], $rows);
    }

    /**
     * Create a new package
     *
     * @param PackageModel $package Package model
     * @return int|false Package ID on success, false on failure
     */
    public static function create(PackageModel $package) {
        global $wpdb;

        $data = $package->to_array();
        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->insert(self::table(), $data, self::get_formats($data));

        if ($result === false) {
            return false;
        }

        return (int) $wpdb->insert_id;
    }

    /**
     * Update an existing package
     *
     * @param PackageModel $package Package model with ID set
     * @return bool
     */
    public static function update(PackageModel $package): bool {
        global $wpdb;

        if (!$package->get_id()) {
            return false;
        }

        $data = $package->to_array();
        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            self::table(),
            $data,
            ['id' => $package->get_id()],
            self::get_formats($data),
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Save package (create or update)
     *
     * @param PackageModel $package Package model
     * @return int|false Package ID on success, false on failure
     */
    public static function save(PackageModel $package) {
        if ($package->get_id()) {
            return self::update($package) ? $package->get_id() : false;
        }
        return self::create($package);
    }

    /**
     * Delete a package
     *
     * @param int $id Package ID
     * @return bool
     */
    public static function delete(int $id): bool {
        global $wpdb;

        $result = $wpdb->delete(self::table(), ['id' => $id], ['%d']);

        return $result !== false;
    }

    /**
     * Delete all packages for a treatment
     *
     * @param int $treatment_id Treatment post ID
     * @return int Number of deleted rows
     */
    public static function delete_by_treatment(int $treatment_id): int {
        global $wpdb;

        return (int) $wpdb->delete(self::table(), ['treatment_id' => $treatment_id], ['%d']);
    }

    /**
     * Count packages for a treatment
     *
     * @param int  $treatment_id Treatment post ID
     * @param bool $active_only  Only count active packages
     * @return int
     */
    public static function count_by_treatment(int $treatment_id, bool $active_only = true): int {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM " . self::table() . " WHERE treatment_id = %d";

        if ($active_only) {
            $sql .= " AND is_active = 1";
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, $treatment_id));
    }

    /**
     * Update sort order for packages
     *
     * @param array $order Array of package_id => sort_order
     * @return bool
     */
    public static function update_sort_order(array $order): bool {
        global $wpdb;

        foreach ($order as $id => $sort) {
            $wpdb->update(
                self::table(),
                ['sort_order' => (int) $sort],
                ['id' => (int) $id],
                ['%d'],
                ['%d']
            );
        }

        return true;
    }

    /**
     * Get format strings for wpdb operations
     *
     * @param array $data Data array
     * @return array
     */
    private static function get_formats(array $data): array {
        $formats = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['treatment_id', 'product_id', 'sessions', 'sort_order', 'is_active'])) {
                $formats[] = '%d';
            } elseif (in_array($key, ['price', 'sale_price', 'deposit_amount'])) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }
}
