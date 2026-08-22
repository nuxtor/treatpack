<?php

namespace TreatmentPackages\Customer;

use TreatmentPackages\DB\Installer;

defined('ABSPATH') || exit;

/**
 * Customer Package Repository
 *
 * CRUD operations for customer packages (purchased packages with session tracking)
 */
class CustomerPackageRepository {

    /**
     * Get table name
     *
     * @return string
     */
    private static function table(): string {
        return Installer::get_customer_packages_table();
    }

    /**
     * Find by ID
     *
     * @param int $id Customer package ID
     * @return array|null
     */
    public static function find(int $id): ?array {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE id = %d",
            $id
        ), ARRAY_A);

        return $row ?: null;
    }

    /**
     * Find by order item ID
     *
     * @param int $order_item_id Order item ID
     * @return array|null
     */
    public static function find_by_order_item(int $order_item_id): ?array {
        global $wpdb;

        $row = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM " . self::table() . " WHERE order_item_id = %d",
            $order_item_id
        ), ARRAY_A);

        return $row ?: null;
    }

    /**
     * Get packages for a customer
     *
     * @param int   $customer_id Customer user ID
     * @param array $args        Query arguments
     * @return array
     */
    public static function get_by_customer(int $customer_id, array $args = []): array {
        global $wpdb;

        $defaults = [
            'status' => null,
            'limit' => null,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT * FROM " . self::table() . " WHERE customer_id = %d";
        $params = [$customer_id];

        if ($args['status']) {
            $sql .= " AND status = %s";
            $params[] = $args['status'];
        }

        $allowed_orderby = ['id', 'created_at', 'total_sessions', 'sessions_used', 'status'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql .= " ORDER BY {$orderby} {$order}";

        if ($args['limit']) {
            $sql .= " LIMIT %d OFFSET %d";
            $params[] = $args['limit'];
            $params[] = $args['offset'];
        }

        return $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);
    }

    /**
     * Get all customer packages with filters
     *
     * @param array $args Query arguments
     * @return array
     */
    public static function get_all(array $args = []): array {
        global $wpdb;

        $defaults = [
            'status' => null,
            'customer_id' => null,
            'treatment_id' => null,
            'search' => null,
            'limit' => 20,
            'offset' => 0,
            'orderby' => 'created_at',
            'order' => 'DESC',
        ];

        $args = wp_parse_args($args, $defaults);

        $sql = "SELECT * FROM " . self::table() . " WHERE 1=1";
        $params = [];

        if ($args['status']) {
            $sql .= " AND status = %s";
            $params[] = $args['status'];
        }

        if ($args['customer_id']) {
            $sql .= " AND customer_id = %d";
            $params[] = $args['customer_id'];
        }

        if ($args['treatment_id']) {
            $sql .= " AND treatment_id = %d";
            $params[] = $args['treatment_id'];
        }

        if ($args['search']) {
            $sql .= " AND (package_name LIKE %s OR treatment_name LIKE %s)";
            $search = '%' . $wpdb->esc_like($args['search']) . '%';
            $params[] = $search;
            $params[] = $search;
        }

        $allowed_orderby = ['id', 'created_at', 'total_sessions', 'sessions_used', 'status', 'customer_id'];
        $orderby = in_array($args['orderby'], $allowed_orderby) ? $args['orderby'] : 'created_at';
        $order = strtoupper($args['order']) === 'ASC' ? 'ASC' : 'DESC';

        $sql .= " ORDER BY {$orderby} {$order}";

        if ($args['limit']) {
            $sql .= " LIMIT %d OFFSET %d";
            $params[] = $args['limit'];
            $params[] = $args['offset'];
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return $wpdb->get_results($sql, ARRAY_A);
    }

    /**
     * Count customer packages
     *
     * @param array $args Query arguments
     * @return int
     */
    public static function count(array $args = []): int {
        global $wpdb;

        $sql = "SELECT COUNT(*) FROM " . self::table() . " WHERE 1=1";
        $params = [];

        if (!empty($args['status'])) {
            $sql .= " AND status = %s";
            $params[] = $args['status'];
        }

        if (!empty($args['customer_id'])) {
            $sql .= " AND customer_id = %d";
            $params[] = $args['customer_id'];
        }

        if (!empty($params)) {
            $sql = $wpdb->prepare($sql, $params);
        }

        return (int) $wpdb->get_var($sql);
    }

    /**
     * Create a new customer package
     *
     * @param array $data Package data
     * @return int|false
     */
    public static function create(array $data) {
        global $wpdb;

        $data['created_at'] = current_time('mysql');
        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->insert(self::table(), $data, self::get_formats($data));

        return $result ? (int) $wpdb->insert_id : false;
    }

    /**
     * Update customer package
     *
     * @param int   $id   Customer package ID
     * @param array $data Data to update
     * @return bool
     */
    public static function update(int $id, array $data): bool {
        global $wpdb;

        $data['updated_at'] = current_time('mysql');

        $result = $wpdb->update(
            self::table(),
            $data,
            ['id' => $id],
            self::get_formats($data),
            ['%d']
        );

        return $result !== false;
    }

    /**
     * Update status
     *
     * @param int    $id     Customer package ID
     * @param string $status New status
     * @return bool
     */
    public static function update_status(int $id, string $status): bool {
        $valid_statuses = ['active', 'completed', 'cancelled', 'expired'];

        if (!in_array($status, $valid_statuses)) {
            return false;
        }

        return self::update($id, ['status' => $status]);
    }

    /**
     * Use a session
     *
     * @param int    $id    Customer package ID
     * @param string $notes Optional notes
     * @return bool
     */
    public static function use_session(int $id, string $notes = ''): bool {
        global $wpdb;

        $package = self::find($id);

        if (!$package || $package['status'] !== 'active') {
            return false;
        }

        $new_used = $package['sessions_used'] + 1;
        $data = ['sessions_used' => $new_used];

        // Auto-complete if all sessions used
        if ($new_used >= $package['total_sessions']) {
            $data['status'] = 'completed';
        }

        if ($notes) {
            $data['notes'] = $package['notes']
                ? $package['notes'] . "\n" . current_time('mysql') . ': ' . $notes
                : current_time('mysql') . ': ' . $notes;
        }

        return self::update($id, $data);
    }

    /**
     * Record balance payment
     *
     * @param int   $id     Customer package ID
     * @param float $amount Payment amount
     * @return bool
     */
    public static function record_payment(int $id, float $amount): bool {
        $package = self::find($id);

        if (!$package) {
            return false;
        }

        $new_paid = $package['balance_paid'] + $amount;

        return self::update($id, ['balance_paid' => $new_paid]);
    }

    /**
     * Get statistics
     *
     * @return array
     */
    public static function get_stats(): array {
        global $wpdb;
        $table = self::table();

        $stats = [
            'total' => 0,
            'active' => 0,
            'completed' => 0,
            'cancelled' => 0,
            'total_sessions' => 0,
            'sessions_used' => 0,
            'sessions_remaining' => 0,
            'outstanding_balance' => 0,
        ];

        // Count by status
        $counts = $wpdb->get_results(
            "SELECT status, COUNT(*) as count FROM {$table} GROUP BY status",
            ARRAY_A
        );

        foreach ($counts as $row) {
            $stats[$row['status']] = (int) $row['count'];
            $stats['total'] += (int) $row['count'];
        }

        // Session stats for active packages
        $session_stats = $wpdb->get_row(
            "SELECT
                SUM(total_sessions) as total_sessions,
                SUM(sessions_used) as sessions_used,
                SUM(balance_due - balance_paid) as outstanding_balance
             FROM {$table}
             WHERE status = 'active'",
            ARRAY_A
        );

        if ($session_stats) {
            $stats['total_sessions'] = (int) ($session_stats['total_sessions'] ?? 0);
            $stats['sessions_used'] = (int) ($session_stats['sessions_used'] ?? 0);
            $stats['sessions_remaining'] = $stats['total_sessions'] - $stats['sessions_used'];
            $stats['outstanding_balance'] = (float) ($session_stats['outstanding_balance'] ?? 0);
        }

        return $stats;
    }

    /**
     * Delete customer package
     *
     * @param int $id Customer package ID
     * @return bool
     */
    public static function delete(int $id): bool {
        global $wpdb;

        return $wpdb->delete(self::table(), ['id' => $id], ['%d']) !== false;
    }

    /**
     * Get format strings for wpdb
     *
     * @param array $data Data array
     * @return array
     */
    private static function get_formats(array $data): array {
        $formats = [];

        foreach ($data as $key => $value) {
            if (in_array($key, ['customer_id', 'order_id', 'order_item_id', 'package_id', 'treatment_id', 'total_sessions', 'sessions_used'])) {
                $formats[] = '%d';
            } elseif (in_array($key, ['total_price', 'deposit_paid', 'balance_due', 'balance_paid'])) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }

        return $formats;
    }
}
