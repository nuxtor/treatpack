<?php

namespace TreatmentPackages\Packages;

use TreatmentPackages\PostTypes\TreatmentPostType;

defined('ABSPATH') || exit;

/**
 * Package Admin UI
 *
 * Meta box for managing packages on treatment edit screen
 */
class PackageAdminUI {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('add_meta_boxes', [$this, 'add_meta_box']);
        add_action('wp_ajax_treatpack_save_package', [$this, 'ajax_save_package']);
        add_action('wp_ajax_treatpack_delete_package', [$this, 'ajax_delete_package']);
        add_action('wp_ajax_treatpack_update_package_order', [$this, 'ajax_update_order']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }

    /**
     * Enqueue admin scripts
     *
     * @param string $hook Current admin page
     */
    public function enqueue_scripts(string $hook): void {
        $screen = get_current_screen();

        if (!$screen || $screen->post_type !== TreatmentPostType::POST_TYPE) {
            return;
        }

        if (!in_array($hook, ['post.php', 'post-new.php'])) {
            return;
        }

        wp_enqueue_style(
            'treatpack-admin-packages',
            TREATPACK_PLUGIN_URL . 'assets/css/admin-packages.css',
            [],
            TREATPACK_VERSION
        );

        wp_enqueue_script(
            'treatpack-admin-packages',
            TREATPACK_PLUGIN_URL . 'assets/js/admin-packages.js',
            ['jquery', 'jquery-ui-sortable'],
            TREATPACK_VERSION,
            true
        );

        wp_localize_script('treatpack-admin-packages', 'treatpackPackages', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('treatpack_packages'),
            'i18n' => [
                'confirm_delete' => __('Are you sure you want to delete this package?', 'treatpack'),
                'saving' => __('Saving...', 'treatpack'),
                'saved' => __('Saved!', 'treatpack'),
                'error' => __('Error saving package', 'treatpack'),
            ],
        ]);
    }

    /**
     * Add meta box
     */
    public function add_meta_box(): void {
        add_meta_box(
            'treatpack_packages',
            __('Treatment Packages', 'treatpack'),
            [$this, 'render_meta_box'],
            TreatmentPostType::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Render meta box content
     *
     * @param \WP_Post $post Current post
     */
    public function render_meta_box(\WP_Post $post): void {
        $packages = PackageRepository::get_by_treatment($post->ID, false);
        ?>
        <div class="treatpack-packages-wrap" data-treatment-id="<?php echo esc_attr($post->ID); ?>">
            <div class="treatpack-packages-list" id="treatpack-packages-list">
                <?php if (empty($packages)): ?>
                    <p class="treatpack-no-packages"><?php esc_html_e('No packages yet. Add your first package below.', 'treatpack'); ?></p>
                <?php else: ?>
                    <?php foreach ($packages as $package): ?>
                        <?php $this->render_package_row($package); ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="treatpack-add-package">
                <h4><?php esc_html_e('Add New Package', 'treatpack'); ?></h4>
                <?php $this->render_package_form(); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render a package row
     *
     * @param PackageModel $package Package model
     */
    private function render_package_row(PackageModel $package): void {
        ?>
        <div class="treatpack-package-row <?php echo $package->is_active() ? '' : 'inactive'; ?>"
             data-package-id="<?php echo esc_attr($package->get_id()); ?>">
            <div class="treatpack-package-handle">
                <span class="dashicons dashicons-menu"></span>
            </div>
            <div class="treatpack-package-info">
                <strong class="treatpack-package-name"><?php echo esc_html($package->get_name()); ?></strong>
                <span class="treatpack-package-details">
                    <?php
                    printf(
                        esc_html__('%d sessions - %s', 'treatpack'),
                        $package->get_sessions(),
                        wc_price($package->get_active_price())
                    );
                    if ($package->get_sale_price()) {
                        echo ' <del>' . wc_price($package->get_price()) . '</del>';
                    }
                    ?>
                </span>
                <?php if (!$package->is_active()): ?>
                    <span class="treatpack-package-badge inactive"><?php esc_html_e('Inactive', 'treatpack'); ?></span>
                <?php endif; ?>
            </div>
            <div class="treatpack-package-actions">
                <button type="button" class="button treatpack-edit-package" title="<?php esc_attr_e('Edit', 'treatpack'); ?>">
                    <span class="dashicons dashicons-edit"></span>
                </button>
                <button type="button" class="button treatpack-delete-package" title="<?php esc_attr_e('Delete', 'treatpack'); ?>">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </div>
            <div class="treatpack-package-edit-form" style="display: none;">
                <?php $this->render_package_form($package); ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render package form
     *
     * @param PackageModel|null $package Existing package for editing
     */
    private function render_package_form(?PackageModel $package = null): void {
        $is_edit = $package !== null;
        $prefix = $is_edit ? 'edit' : 'new';
        ?>
        <div class="treatpack-package-form">
            <input type="hidden" name="package_id" value="<?php echo $is_edit ? esc_attr($package->get_id()) : ''; ?>">

            <div class="treatpack-form-row">
                <div class="treatpack-form-field">
                    <label><?php esc_html_e('Package Name', 'treatpack'); ?> <span class="required">*</span></label>
                    <input type="text" name="name" value="<?php echo $is_edit ? esc_attr($package->get_name()) : ''; ?>" required>
                </div>
                <div class="treatpack-form-field treatpack-form-field-small">
                    <label><?php esc_html_e('Sessions', 'treatpack'); ?> <span class="required">*</span></label>
                    <input type="number" name="sessions" value="<?php echo $is_edit ? esc_attr($package->get_sessions()) : '1'; ?>" min="1" required>
                </div>
            </div>

            <div class="treatpack-form-row">
                <div class="treatpack-form-field">
                    <label><?php esc_html_e('Price', 'treatpack'); ?> <span class="required">*</span></label>
                    <input type="number" name="price" value="<?php echo $is_edit ? esc_attr($package->get_price()) : ''; ?>" step="0.01" min="0" required>
                </div>
                <div class="treatpack-form-field">
                    <label><?php esc_html_e('Sale Price', 'treatpack'); ?></label>
                    <input type="number" name="sale_price" value="<?php echo $is_edit && $package->get_sale_price() ? esc_attr($package->get_sale_price()) : ''; ?>" step="0.01" min="0">
                </div>
            </div>

            <div class="treatpack-form-row">
                <div class="treatpack-form-field">
                    <label><?php esc_html_e('Deposit Type', 'treatpack'); ?></label>
                    <select name="deposit_type">
                        <option value="none" <?php echo $is_edit ? selected($package->get_deposit_type(), 'none', false) : ''; ?>>
                            <?php esc_html_e('No deposit (full payment)', 'treatpack'); ?>
                        </option>
                        <option value="fixed" <?php echo $is_edit ? selected($package->get_deposit_type(), 'fixed', false) : ''; ?>>
                            <?php esc_html_e('Fixed amount', 'treatpack'); ?>
                        </option>
                        <option value="percentage" <?php echo $is_edit ? selected($package->get_deposit_type(), 'percentage', false) : ''; ?>>
                            <?php esc_html_e('Percentage', 'treatpack'); ?>
                        </option>
                        <option value="pay_at_location" <?php echo $is_edit ? selected($package->get_deposit_type(), 'pay_at_location', false) : ''; ?>>
                            <?php esc_html_e('Pay at location', 'treatpack'); ?>
                        </option>
                    </select>
                </div>
                <div class="treatpack-form-field treatpack-deposit-amount-field">
                    <label><?php esc_html_e('Deposit Amount', 'treatpack'); ?></label>
                    <input type="number" name="deposit_amount" value="<?php echo $is_edit && $package->get_deposit_amount() ? esc_attr($package->get_deposit_amount()) : ''; ?>" step="0.01" min="0">
                </div>
            </div>

            <div class="treatpack-form-row">
                <div class="treatpack-form-field treatpack-form-field-full">
                    <label><?php esc_html_e('Description', 'treatpack'); ?></label>
                    <textarea name="description" rows="2"><?php echo $is_edit && $package->get_description() ? esc_textarea($package->get_description()) : ''; ?></textarea>
                </div>
            </div>

            <div class="treatpack-form-row">
                <div class="treatpack-form-field">
                    <label>
                        <input type="checkbox" name="is_active" value="1" <?php echo !$is_edit || $package->is_active() ? 'checked' : ''; ?>>
                        <?php esc_html_e('Active', 'treatpack'); ?>
                    </label>
                </div>
            </div>

            <div class="treatpack-form-actions">
                <button type="button" class="button button-primary treatpack-save-package">
                    <?php echo $is_edit ? esc_html__('Update Package', 'treatpack') : esc_html__('Add Package', 'treatpack'); ?>
                </button>
                <?php if ($is_edit): ?>
                    <button type="button" class="button treatpack-cancel-edit">
                        <?php esc_html_e('Cancel', 'treatpack'); ?>
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * AJAX: Save package
     */
    public function ajax_save_package(): void {
        check_ajax_referer('treatpack_packages', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied', 'treatpack')]);
        }

        $treatment_id = absint($_POST['treatment_id'] ?? 0);
        $package_id = absint($_POST['package_id'] ?? 0);

        if (!$treatment_id) {
            wp_send_json_error(['message' => __('Invalid treatment', 'treatpack')]);
        }

        $package = $package_id ? PackageRepository::find($package_id) : new PackageModel();

        if (!$package) {
            wp_send_json_error(['message' => __('Package not found', 'treatpack')]);
        }

        $package->set_treatment_id($treatment_id)
            ->set_name(sanitize_text_field($_POST['name'] ?? ''))
            ->set_sessions(absint($_POST['sessions'] ?? 1))
            ->set_price((float) ($_POST['price'] ?? 0))
            ->set_sale_price(isset($_POST['sale_price']) && $_POST['sale_price'] !== '' ? (float) $_POST['sale_price'] : null)
            ->set_deposit_type(sanitize_text_field($_POST['deposit_type'] ?? 'none'))
            ->set_deposit_amount(isset($_POST['deposit_amount']) && $_POST['deposit_amount'] !== '' ? (float) $_POST['deposit_amount'] : null)
            ->set_description(sanitize_textarea_field($_POST['description'] ?? ''))
            ->set_is_active(isset($_POST['is_active']) && $_POST['is_active'] === '1');

        if (empty($package->get_name())) {
            wp_send_json_error(['message' => __('Package name is required', 'treatpack')]);
        }

        $saved_id = PackageRepository::save($package);

        if (!$saved_id) {
            wp_send_json_error(['message' => __('Error saving package', 'treatpack')]);
        }

        $package->set_id($saved_id);

        // Sync with WooCommerce product
        do_action('treatpack_package_saved', $package);

        ob_start();
        $this->render_package_row($package);
        $html = ob_get_clean();

        wp_send_json_success([
            'message' => __('Package saved', 'treatpack'),
            'package_id' => $saved_id,
            'html' => $html,
        ]);
    }

    /**
     * AJAX: Delete package
     */
    public function ajax_delete_package(): void {
        check_ajax_referer('treatpack_packages', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied', 'treatpack')]);
        }

        $package_id = absint($_POST['package_id'] ?? 0);

        if (!$package_id) {
            wp_send_json_error(['message' => __('Invalid package', 'treatpack')]);
        }

        $package = PackageRepository::find($package_id);

        if (!$package) {
            wp_send_json_error(['message' => __('Package not found', 'treatpack')]);
        }

        // Trigger action before deletion (for WooCommerce cleanup)
        do_action('treatpack_before_package_delete', $package);

        if (!PackageRepository::delete($package_id)) {
            wp_send_json_error(['message' => __('Error deleting package', 'treatpack')]);
        }

        wp_send_json_success(['message' => __('Package deleted', 'treatpack')]);
    }

    /**
     * AJAX: Update package order
     */
    public function ajax_update_order(): void {
        check_ajax_referer('treatpack_packages', 'nonce');

        if (!current_user_can('edit_posts')) {
            wp_send_json_error(['message' => __('Permission denied', 'treatpack')]);
        }

        $order = $_POST['order'] ?? [];

        if (empty($order) || !is_array($order)) {
            wp_send_json_error(['message' => __('Invalid order data', 'treatpack')]);
        }

        $order_map = [];
        foreach ($order as $index => $package_id) {
            $order_map[(int) $package_id] = (int) $index;
        }

        PackageRepository::update_sort_order($order_map);

        wp_send_json_success(['message' => __('Order updated', 'treatpack')]);
    }
}
