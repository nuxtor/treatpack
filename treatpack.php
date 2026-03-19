<?php
/**
 * Plugin Name: TreatPack - Treatment Packages for WooCommerce
 * Plugin URI: https://niftycs.uk/treatpack
 * Description: Sell treatment packages with multi-session pricing, automatic deposits, and session tracking — powered by WooCommerce.
 * Version: 1.0.0
 * Author: Wajira Sampath
 * Author URI: https://niftycs.uk
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: treatpack
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * WC requires at least: 10.0
 * WC tested up to: 10.3.6
 *
 * @package TreatPack
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
function tpd_is_woocommerce_active() {
    return class_exists( 'WooCommerce' );
}

/**
 * Display admin notice if WooCommerce is not active
 */
function tpd_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <?php
            printf(
                /* translators: %s: WooCommerce plugin name */
                esc_html__( '%1$s requires %2$s to be installed and active.', 'treatpack' ),
                '<strong>TreatPack</strong>',
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
function tpd_init() {
    if ( ! tpd_is_woocommerce_active() ) {
        add_action( 'admin_notices', 'tpd_woocommerce_missing_notice' );
        return;
    }

    // Load text domain.
    // phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- Required for custom language directory support.
    load_plugin_textdomain(
        'treatpack',
        false,
        dirname( TP_DEPOSITS_PLUGIN_BASENAME ) . '/languages'
    );

    // Initialize the plugin
    \TreatmentPackages\Plugin::instance();
}
add_action( 'plugins_loaded', 'tpd_init' );

/**
 * Plugin activation hook
 */
function tpd_activate() {
    if ( ! tpd_is_woocommerce_active() ) {
        deactivate_plugins( TP_DEPOSITS_PLUGIN_BASENAME );
        wp_die(
            esc_html__( 'TreatPack requires WooCommerce to be installed and active.', 'treatpack' ),
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
register_activation_hook( __FILE__, 'tpd_activate' );

/**
 * Plugin deactivation hook
 */
function tpd_deactivate() {
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'tpd_deactivate' );

/**
 * Declare HPOS compatibility for WooCommerce
 */
add_action( 'before_woocommerce_init', function() {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
    }
});
