<?php
/**
 * Plugin Name: TreatPack
 * Plugin URI: https://treatpack.lemonsqueezy.com/
 * Description: Treatment package management for WooCommerce - sell multi-session packages for clinics, salons, spas, and wellness centers.
 * Version: 1.0.0
 * Author: TreatPack
 * Author URI: https://treatpack.lemonsqueezy.com/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: treatpack
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * WC tested up to: 9.0
 */

defined('ABSPATH') || exit;

// Plugin constants
define('TREATPACK_VERSION', '1.0.0');
define('TREATPACK_PLUGIN_FILE', __FILE__);
define('TREATPACK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('TREATPACK_PLUGIN_URL', plugin_dir_url(__FILE__));
define('TREATPACK_PLUGIN_BASENAME', plugin_basename(__FILE__));

/**
 * Check if WooCommerce is active
 */
function treatpack_check_woocommerce() {
    if (!class_exists('WooCommerce')) {
        add_action('admin_notices', 'treatpack_woocommerce_missing_notice');
        return false;
    }
    return true;
}

/**
 * Admin notice for missing WooCommerce
 */
function treatpack_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p><?php esc_html_e('TreatPack requires WooCommerce to be installed and active.', 'treatpack'); ?></p>
    </div>
    <?php
}

/**
 * PSR-4 Autoloader
 */
spl_autoload_register(function ($class) {
    $prefix = 'TreatmentPackages\\';
    $base_dir = TREATPACK_PLUGIN_DIR . 'src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});

/**
 * Plugin activation hook
 */
function treatpack_activate() {
    if (!treatpack_check_woocommerce()) {
        deactivate_plugins(TREATPACK_PLUGIN_BASENAME);
        wp_die(
            esc_html__('TreatPack requires WooCommerce to be installed and active.', 'treatpack'),
            'Plugin Activation Error',
            ['back_link' => true]
        );
    }

    // Run database installer
    \TreatmentPackages\DB\Installer::install();

    // Flush rewrite rules for custom post types
    flush_rewrite_rules();
}
register_activation_hook(__FILE__, 'treatpack_activate');

/**
 * Plugin deactivation hook
 */
function treatpack_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook(__FILE__, 'treatpack_deactivate');

/**
 * Initialize the plugin
 */
function treatpack_init() {
    if (!treatpack_check_woocommerce()) {
        return;
    }

    // Load text domain
    load_plugin_textdomain('treatpack', false, dirname(TREATPACK_PLUGIN_BASENAME) . '/languages');

    // Initialize main plugin class
    \TreatmentPackages\Plugin::instance();
}
add_action('plugins_loaded', 'treatpack_init');

/**
 * Declare HPOS compatibility
 */
add_action('before_woocommerce_init', function () {
    if (class_exists(\Automattic\WooCommerce\Utilities\FeaturesUtil::class)) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
    }
});
