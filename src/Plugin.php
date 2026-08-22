<?php

namespace TreatmentPackages;

defined('ABSPATH') || exit;

/**
 * Main Plugin Class
 *
 * Singleton pattern - orchestrates all plugin components
 */
final class Plugin {

    /**
     * Plugin instance
     *
     * @var Plugin|null
     */
    private static ?Plugin $instance = null;

    /**
     * Get plugin instance
     *
     * @return Plugin
     */
    public static function instance(): Plugin {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        $this->init_hooks();
        $this->init_components();
    }

    /**
     * Initialize WordPress hooks
     */
    private function init_hooks(): void {
        add_action('init', [$this, 'register_post_types'], 5);
        add_action('admin_enqueue_scripts', [$this, 'admin_assets']);
        add_action('wp_enqueue_scripts', [$this, 'frontend_assets']);
    }

    /**
     * Initialize plugin components
     */
    private function init_components(): void {
        // Post Types
        new PostTypes\TreatmentPostType();
        new PostTypes\TreatmentTaxonomies();

        // Packages
        new Packages\PackageAdminUI();

        // WooCommerce Integration
        new Woo\ProductSync();
        new Woo\CartHandler();
        new Woo\OrderHandler();

        // Frontend
        new Frontend\Shortcodes();

        // Admin
        if (is_admin()) {
            new Admin\CustomerPackages();
            new Admin\ImportExport();
        }
    }

    /**
     * Register custom post types
     */
    public function register_post_types(): void {
        // Handled by TreatmentPostType class
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook
     */
    public function admin_assets(string $hook): void {
        $screen = get_current_screen();

        if (!$screen) {
            return;
        }

        // Only load on treatment post type screens
        if ($screen->post_type === 'tp_treatment') {
            wp_enqueue_style(
                'treatpack-admin',
                TREATPACK_PLUGIN_URL . 'assets/css/admin.css',
                [],
                TREATPACK_VERSION
            );

            wp_enqueue_script(
                'treatpack-admin',
                TREATPACK_PLUGIN_URL . 'assets/js/admin.js',
                ['jquery'],
                TREATPACK_VERSION,
                true
            );

            wp_localize_script('treatpack-admin', 'treatpack', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('treatpack_admin'),
            ]);
        }
    }

    /**
     * Enqueue frontend assets
     */
    public function frontend_assets(): void {
        wp_enqueue_style(
            'treatpack-frontend',
            TREATPACK_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            TREATPACK_VERSION
        );

        wp_enqueue_script(
            'treatpack-frontend',
            TREATPACK_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            TREATPACK_VERSION,
            true
        );

        wp_localize_script('treatpack-frontend', 'treatpack', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('treatpack_frontend'),
        ]);
    }

    /**
     * Prevent cloning
     */
    private function __clone() {}

    /**
     * Prevent unserialization
     */
    public function __wakeup() {
        throw new \Exception('Cannot unserialize singleton');
    }
}
