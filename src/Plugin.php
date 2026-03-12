<?php
/**
 * Main Plugin Class
 *
 * @package TreatmentPackages
 */

namespace TreatmentPackages;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin Class
 *
 * Singleton class that initializes and manages all plugin components.
 */
final class Plugin {

    /**
     * Plugin version
     *
     * @var string
     */
    public $version = '1.0.0';

    /**
     * Single instance of the class
     *
     * @var Plugin|null
     */
    private static $instance = null;

    /**
     * Get the singleton instance
     *
     * @return Plugin
     */
    public static function instance() {
        if ( is_null( self::$instance ) ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor - private to enforce singleton pattern
     */
    private function __construct() {
        $this->define_constants();
        $this->init_hooks();
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     *
     * @throws \Exception When attempting to unserialize.
     */
    public function __wakeup() {
        throw new \Exception( 'Cannot unserialize singleton' );
    }

    /**
     * Define additional constants if needed
     */
    private function define_constants() {
        // Additional constants can be defined here
    }

    /**
     * Initialize hooks
     */
    private function init_hooks() {
        add_action( 'init', array( $this, 'init' ), 0 );
        add_action( 'admin_init', array( $this, 'admin_init' ) );

        // Initialize admin components
        if ( is_admin() ) {
            $this->init_admin();
        }
    }

    /**
     * Initialize admin components
     */
    private function init_admin() {
        Packages\PackageAdminUI::init();
        Admin\AdminMenu::init();
        Admin\ImportExport::init();
    }

    /**
     * Initialize plugin components on 'init' hook
     */
    public function init() {
        // Initialize Post Types
        $this->init_post_types();

        // Initialize Frontend
        $this->init_frontend();

        // Initialize WooCommerce integration
        $this->init_woocommerce();
    }

    /**
     * Initialize admin-specific components
     */
    public function admin_init() {
        // Admin initialization
    }

    /**
     * Initialize custom post types and taxonomies
     */
    private function init_post_types() {
        PostTypes\TreatmentPostType::register();
        PostTypes\TreatmentTaxonomies::register();
    }

    /**
     * Initialize frontend components
     */
    private function init_frontend() {
        // Shortcodes (Phase 9)
        Frontend\Shortcodes::init();

        // Enqueue frontend assets
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_frontend_assets' ) );
    }

    /**
     * Initialize WooCommerce integration
     */
    private function init_woocommerce() {
        // Product sync (Phase 6)
        Woo\ProductsSync::init();

        // Cart handling with deposits (Phase 7)
        Woo\CartHandler::init();

        // Order handling (Phase 8)
        Woo\OrderHandler::init();
    }

    /**
     * Enqueue frontend styles and scripts
     */
    public function enqueue_frontend_assets() {
        // Only enqueue on pages that use our shortcode
        wp_register_style(
            'tp-deposits-frontend',
            TP_DEPOSITS_PLUGIN_URL . 'assets/css/frontend.css',
            array(),
            $this->version
        );

        wp_register_script(
            'tp-deposits-frontend',
            TP_DEPOSITS_PLUGIN_URL . 'assets/js/frontend.js',
            array( 'jquery' ),
            $this->version,
            true
        );

        // Localize script with AJAX URL and nonce
        wp_localize_script(
            'tp-deposits-frontend',
            'tpDeposits',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'cartUrl' => wc_get_cart_url(),
                'nonce'   => wp_create_nonce( 'tp_deposits_nonce' ),
                'i18n'    => array(
                    'addingToCart'  => __( 'Adding...', 'treatpack' ),
                    'addedToCart'   => __( 'Added to cart!', 'treatpack' ),
                    'error'         => __( 'An error occurred. Please try again.', 'treatpack' ),
                    'noTreatments'  => __( 'No treatments found in this category.', 'treatpack' ),
                ),
            )
        );
    }

    /**
     * Get the plugin path
     *
     * @return string
     */
    public function plugin_path() {
        return TP_DEPOSITS_PLUGIN_DIR;
    }

    /**
     * Get the plugin URL
     *
     * @return string
     */
    public function plugin_url() {
        return TP_DEPOSITS_PLUGIN_URL;
    }

    /**
     * Get the assets URL
     *
     * @return string
     */
    public function assets_url() {
        return TP_DEPOSITS_PLUGIN_URL . 'assets/';
    }
}
