<?php

namespace TreatmentPackages\Admin;

use TreatmentPackages\Customer\CustomerPackageRepository;

defined('ABSPATH') || exit;

/**
 * Customer Packages Admin Dashboard
 *
 * Manages customer packages and session tracking
 */
class CustomerPackages {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);

        // AJAX handlers
        add_action('wp_ajax_treatpack_use_session', [$this, 'ajax_use_session']);
        add_action('wp_ajax_treatpack_record_payment', [$this, 'ajax_record_payment']);
        add_action('wp_ajax_treatpack_update_status', [$this, 'ajax_update_status']);
    }

    /**
     * Add admin menu
     */
    public function add_menu(): void {
        add_submenu_page(
            'edit.php?post_type=tp_treatment',
            __('Customer Packages', 'treatpack'),
            __('Customer Packages', 'treatpack'),
            'manage_woocommerce',
            'treatpack-customer-packages',
            [$this, 'render_page']
        );
    }

    /**
     * Enqueue scripts
     *
     * @param string $hook Current admin page
     */
    public function enqueue_scripts(string $hook): void {
        if ($hook !== 'tp_treatment_page_treatpack-customer-packages') {
            return;
        }

        wp_enqueue_style(
            'treatpack-admin-customer-packages',
            TREATPACK_PLUGIN_URL . 'assets/css/admin-customer-packages.css',
            [],
            TREATPACK_VERSION
        );

        wp_enqueue_script(
            'treatpack-admin-customer-packages',
            TREATPACK_PLUGIN_URL . 'assets/js/admin-customer-packages.js',
            ['jquery'],
            TREATPACK_VERSION,
            true
        );

        wp_localize_script('treatpack-admin-customer-packages', 'treatpackCustomer', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('treatpack_customer'),
            'i18n' => [
                'confirm_use_session' => __('Mark one session as used?', 'treatpack'),
                'confirm_status_change' => __('Change package status?', 'treatpack'),
                'enter_amount' => __('Enter payment amount:', 'treatpack'),
            ],
        ]);
    }

    /**
     * Render admin page
     */
    public function render_page(): void {
        $stats = CustomerPackageRepository::get_stats();

        // Handle filters
        $current_status = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : '';
        $current_customer = isset($_GET['customer_id']) ? absint($_GET['customer_id']) : 0;
        $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
        $paged = isset($_GET['paged']) ? absint($_GET['paged']) : 1;
        $per_page = 20;

        $args = [
            'status' => $current_status ?: null,
            'customer_id' => $current_customer ?: null,
            'search' => $search ?: null,
            'limit' => $per_page,
            'offset' => ($paged - 1) * $per_page,
        ];

        $packages = CustomerPackageRepository::get_all($args);
        $total = CustomerPackageRepository::count($args);
        $total_pages = ceil($total / $per_page);
        ?>
        <div class="wrap treatpack-customer-packages">
            <h1><?php esc_html_e('Customer Packages', 'treatpack'); ?></h1>

            <!-- Stats Cards -->
            <div class="treatpack-stats-cards">
                <div class="treatpack-stat-card">
                    <span class="stat-value"><?php echo esc_html($stats['active']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Active Packages', 'treatpack'); ?></span>
                </div>
                <div class="treatpack-stat-card">
                    <span class="stat-value"><?php echo esc_html($stats['sessions_remaining']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Sessions Remaining', 'treatpack'); ?></span>
                </div>
                <div class="treatpack-stat-card">
                    <span class="stat-value"><?php echo wc_price($stats['outstanding_balance']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Outstanding Balance', 'treatpack'); ?></span>
                </div>
                <div class="treatpack-stat-card">
                    <span class="stat-value"><?php echo esc_html($stats['completed']); ?></span>
                    <span class="stat-label"><?php esc_html_e('Completed', 'treatpack'); ?></span>
                </div>
            </div>

            <!-- Filters -->
            <div class="treatpack-filters">
                <form method="get">
                    <input type="hidden" name="post_type" value="tp_treatment">
                    <input type="hidden" name="page" value="treatpack-customer-packages">

                    <select name="status">
                        <option value=""><?php esc_html_e('All Statuses', 'treatpack'); ?></option>
                        <option value="active" <?php selected($current_status, 'active'); ?>><?php esc_html_e('Active', 'treatpack'); ?></option>
                        <option value="completed" <?php selected($current_status, 'completed'); ?>><?php esc_html_e('Completed', 'treatpack'); ?></option>
                        <option value="cancelled" <?php selected($current_status, 'cancelled'); ?>><?php esc_html_e('Cancelled', 'treatpack'); ?></option>
                        <option value="expired" <?php selected($current_status, 'expired'); ?>><?php esc_html_e('Expired', 'treatpack'); ?></option>
                    </select>

                    <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search...', 'treatpack'); ?>">

                    <button type="submit" class="button"><?php esc_html_e('Filter', 'treatpack'); ?></button>

                    <?php if ($current_status || $search || $current_customer): ?>
                        <a href="<?php echo esc_url(admin_url('edit.php?post_type=tp_treatment&page=treatpack-customer-packages')); ?>" class="button">
                            <?php esc_html_e('Clear', 'treatpack'); ?>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Packages Table -->
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('Customer', 'treatpack'); ?></th>
                        <th><?php esc_html_e('Treatment', 'treatpack'); ?></th>
                        <th><?php esc_html_e('Package', 'treatpack'); ?></th>
                        <th><?php esc_html_e('Sessions', 'treatpack'); ?></th>
                        <th><?php esc_html_e('Balance', 'treatpack'); ?></th>
                        <th><?php esc_html_e('Status', 'treatpack'); ?></th>
                        <th><?php esc_html_e('Order', 'treatpack'); ?></th>
                        <th><?php esc_html_e('Actions', 'treatpack'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($packages)): ?>
                        <tr>
                            <td colspan="8"><?php esc_html_e('No customer packages found.', 'treatpack'); ?></td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($packages as $package): ?>
                            <?php $this->render_package_row($package); ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="tablenav bottom">
                    <div class="tablenav-pages">
                        <?php
                        $page_links = paginate_links([
                            'base' => add_query_arg('paged', '%#%'),
                            'format' => '',
                            'prev_text' => '&laquo;',
                            'next_text' => '&raquo;',
                            'total' => $total_pages,
                            'current' => $paged,
                        ]);
                        echo $page_links;
                        ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render a package row
     *
     * @param array $package Package data
     */
    private function render_package_row(array $package): void {
        $customer = get_user_by('id', $package['customer_id']);
        $remaining = $package['total_sessions'] - $package['sessions_used'];
        $balance_remaining = $package['balance_due'] - $package['balance_paid'];

        $status_classes = [
            'active' => 'status-active',
            'completed' => 'status-completed',
            'cancelled' => 'status-cancelled',
            'expired' => 'status-expired',
        ];

        $status_labels = [
            'active' => __('Active', 'treatpack'),
            'completed' => __('Completed', 'treatpack'),
            'cancelled' => __('Cancelled', 'treatpack'),
            'expired' => __('Expired', 'treatpack'),
        ];
        ?>
        <tr data-package-id="<?php echo esc_attr($package['id']); ?>">
            <td>
                <?php if ($customer): ?>
                    <strong><?php echo esc_html($customer->display_name); ?></strong>
                    <br><small><?php echo esc_html($customer->user_email); ?></small>
                <?php else: ?>
                    <em><?php esc_html_e('Guest', 'treatpack'); ?></em>
                <?php endif; ?>
            </td>
            <td>
                <a href="<?php echo esc_url(get_edit_post_link($package['treatment_id'])); ?>">
                    <?php echo esc_html($package['treatment_name']); ?>
                </a>
            </td>
            <td><?php echo esc_html($package['package_name']); ?></td>
            <td>
                <span class="sessions-display">
                    <strong><?php echo esc_html($package['sessions_used']); ?></strong> / <?php echo esc_html($package['total_sessions']); ?>
                </span>
                <br>
                <small><?php echo sprintf(esc_html__('%d remaining', 'treatpack'), $remaining); ?></small>
            </td>
            <td>
                <?php if ($package['balance_due'] > 0): ?>
                    <?php if ($balance_remaining > 0): ?>
                        <span class="balance-due"><?php echo wc_price($balance_remaining); ?></span>
                        <br><small><?php esc_html_e('remaining', 'treatpack'); ?></small>
                    <?php else: ?>
                        <span class="balance-paid"><?php esc_html_e('Paid', 'treatpack'); ?></span>
                    <?php endif; ?>
                <?php else: ?>
                    <span class="balance-na">—</span>
                <?php endif; ?>
            </td>
            <td>
                <span class="status-badge <?php echo esc_attr($status_classes[$package['status']] ?? ''); ?>">
                    <?php echo esc_html($status_labels[$package['status']] ?? $package['status']); ?>
                </span>
            </td>
            <td>
                <a href="<?php echo esc_url(admin_url('post.php?post=' . $package['order_id'] . '&action=edit')); ?>">
                    #<?php echo esc_html($package['order_id']); ?>
                </a>
            </td>
            <td class="actions">
                <?php if ($package['status'] === 'active'): ?>
                    <?php if ($remaining > 0): ?>
                        <button type="button" class="button button-small treatpack-use-session" title="<?php esc_attr_e('Use Session', 'treatpack'); ?>">
                            <span class="dashicons dashicons-yes"></span>
                        </button>
                    <?php endif; ?>

                    <?php if ($balance_remaining > 0): ?>
                        <button type="button" class="button button-small treatpack-record-payment" title="<?php esc_attr_e('Record Payment', 'treatpack'); ?>">
                            <span class="dashicons dashicons-money-alt"></span>
                        </button>
                    <?php endif; ?>
                <?php endif; ?>

                <button type="button" class="button button-small treatpack-change-status" title="<?php esc_attr_e('Change Status', 'treatpack'); ?>">
                    <span class="dashicons dashicons-edit"></span>
                </button>

                <div class="status-dropdown" style="display: none;">
                    <select class="status-select">
                        <option value="active" <?php selected($package['status'], 'active'); ?>><?php esc_html_e('Active', 'treatpack'); ?></option>
                        <option value="completed" <?php selected($package['status'], 'completed'); ?>><?php esc_html_e('Completed', 'treatpack'); ?></option>
                        <option value="cancelled" <?php selected($package['status'], 'cancelled'); ?>><?php esc_html_e('Cancelled', 'treatpack'); ?></option>
                        <option value="expired" <?php selected($package['status'], 'expired'); ?>><?php esc_html_e('Expired', 'treatpack'); ?></option>
                    </select>
                    <button type="button" class="button button-small treatpack-save-status"><?php esc_html_e('Save', 'treatpack'); ?></button>
                </div>
            </td>
        </tr>
        <?php
    }

    /**
     * AJAX: Use session
     */
    public function ajax_use_session(): void {
        check_ajax_referer('treatpack_customer', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied', 'treatpack')]);
        }

        $package_id = absint($_POST['package_id'] ?? 0);

        if (!$package_id) {
            wp_send_json_error(['message' => __('Invalid package', 'treatpack')]);
        }

        if (!CustomerPackageRepository::use_session($package_id)) {
            wp_send_json_error(['message' => __('Could not use session', 'treatpack')]);
        }

        $package = CustomerPackageRepository::find($package_id);

        wp_send_json_success([
            'message' => __('Session marked as used', 'treatpack'),
            'sessions_used' => $package['sessions_used'],
            'sessions_remaining' => $package['total_sessions'] - $package['sessions_used'],
            'status' => $package['status'],
        ]);
    }

    /**
     * AJAX: Record payment
     */
    public function ajax_record_payment(): void {
        check_ajax_referer('treatpack_customer', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied', 'treatpack')]);
        }

        $package_id = absint($_POST['package_id'] ?? 0);
        $amount = (float) ($_POST['amount'] ?? 0);

        if (!$package_id || $amount <= 0) {
            wp_send_json_error(['message' => __('Invalid data', 'treatpack')]);
        }

        if (!CustomerPackageRepository::record_payment($package_id, $amount)) {
            wp_send_json_error(['message' => __('Could not record payment', 'treatpack')]);
        }

        $package = CustomerPackageRepository::find($package_id);
        $balance_remaining = $package['balance_due'] - $package['balance_paid'];

        wp_send_json_success([
            'message' => __('Payment recorded', 'treatpack'),
            'balance_remaining' => $balance_remaining,
            'formatted_balance' => $balance_remaining > 0 ? wc_price($balance_remaining) : __('Paid', 'treatpack'),
        ]);
    }

    /**
     * AJAX: Update status
     */
    public function ajax_update_status(): void {
        check_ajax_referer('treatpack_customer', 'nonce');

        if (!current_user_can('manage_woocommerce')) {
            wp_send_json_error(['message' => __('Permission denied', 'treatpack')]);
        }

        $package_id = absint($_POST['package_id'] ?? 0);
        $status = sanitize_text_field($_POST['status'] ?? '');

        if (!$package_id || !$status) {
            wp_send_json_error(['message' => __('Invalid data', 'treatpack')]);
        }

        if (!CustomerPackageRepository::update_status($package_id, $status)) {
            wp_send_json_error(['message' => __('Could not update status', 'treatpack')]);
        }

        wp_send_json_success([
            'message' => __('Status updated', 'treatpack'),
            'status' => $status,
        ]);
    }
}
