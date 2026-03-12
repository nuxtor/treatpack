<?php
/**
 * Plugin Name: Treatment Packages & Deposits
 * Plugin URI: https://niftycs/wp-solutions/plugins/treatment-packages-deposits
 * Description: WooCommerce extension for selling treatment packages with multiple sessions, automatic deposits, and session tracking.
 * Version: 1.0.0
 * Author: Wajira Sampath
 * Author URI: https://niftycs.uk
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: treatment-packages-deposits
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * WC requires at least: 10.0
 * WC tested up to: 10.3.6
 *
 * @package TreatmentPackages
 */

defined( 'ABSPATH' ) || exit;

/**
 * Plugin constants
 */
define( 'TP_DEPOSITS_VERSION', '1.0.0' );
define( 'TP_DEPOSITS_PLUGIN_FILE', __FILE__ );
define( 'TP_DEPOSITS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TP_DEPOSITS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TP_DEPOSITS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * PSR-4 Autoloader for TreatmentPackages namespace
 */
spl_autoload_register( function ( $class ) {
    $prefix = 'TreatmentPackages\\';
    $base_dir = TP_DEPOSITS_PLUGIN_DIR . 'src/';

    $len = strlen( $prefix );
    if ( strncmp( $prefix, $class, $len ) !== 0 ) {
        return;
    }

    $relative_class = substr( $class, $len );
    $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

    if ( file_exists( $file ) ) {
        require $file;
    }
});

/**
 * Check if WooCommerce is active
 *
 * @return bool
 */
function tp_deposits_is_woocommerce_active() {
    return class_exists( 'WooCommerce' );
}

/**
 * Display admin notice if WooCommerce is not active
 */
function tp_deposits_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <?php
            printf(
                /* translators: %s: WooCommerce plugin name */
                esc_html__( '%1$s requires %2$s to be installed and active.', 'treatment-packages-deposits' ),
                '<strong>Treatment Packages & Deposits</strong>',
                '<strong>WooCommerce</strong>'
            );
            ?>
        </p>
    </div>
    <?php
}

/**
 * Initialize the plugin
 */
function tp_deposits_init() {
    if ( ! tp_deposits_is_woocommerce_active() ) {
        add_action( 'admin_notices', 'tp_deposits_woocommerce_missing_notice' );
        return;
    }

    // Load text domain
    load_plugin_textdomain(
        'treatment-packages-deposits',
        false,
        dirname( TP_DEPOSITS_PLUGIN_BASENAME ) . '/languages'
    );

    // Initialize the plugin
    \TreatmentPackages\Plugin::instance();
}
add_action( 'plugins_loaded', 'tp_deposits_init' );

/**
 * Plugin activation hook
 */
function tp_deposits_activate() {
    if ( ! tp_deposits_is_woocommerce_active() ) {
        deactivate_plugins( TP_DEPOSITS_PLUGIN_BASENAME );
        wp_die(
            esc_html__( 'Treatment Packages & Deposits requires WooCommerce to be installed and active.', 'treatment-packages-deposits' ),
            'Plugin Activation Error',
            array( 'back_link' => true )
        );
    }

    // Trigger installer
    require_once TP_DEPOSITS_PLUGIN_DIR . 'src/DB/Installer.php';
    \TreatmentPackages\DB\Installer::install();

    // Flush rewrite rules for custom post types
    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'tp_deposits_activate' );

/**
 * Plugin deactivation hook
 */
function tp_deposits_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'tp_deposits_deactivate' );

/**
 * Declare HPOS compatibility for WooCommerce
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
});
