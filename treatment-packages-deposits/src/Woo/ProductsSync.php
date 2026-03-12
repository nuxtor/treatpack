<?php
/**
 * WooCommerce Products Sync
 *
 * @package TreatmentPackages\Woo
 */

namespace TreatmentPackages\Woo;

use TreatmentPackages\Packages\PackageModel;
use TreatmentPackages\Packages\PackageRepository;

defined( 'ABSPATH' ) || exit;

/**
 * ProductsSync Class
 *
 * Handles synchronization between treatment packages and WooCommerce products.
 * Creates hidden virtual products for each package that can be added to cart.
 */
class ProductsSync {

    /**
     * Product type for synced products
     *
     * @var string
     */
    const PRODUCT_TYPE = 'simple';

    /**
     * Meta key to identify synced products
     *
     * @var string
     */
    const META_KEY_PACKAGE_ID = '_tp_package_id';

    /**
     * Meta key for treatment ID
     *
     * @var string
     */
    const META_KEY_TREATMENT_ID = '_tp_treatment_id';

    /**
     * Initialize the sync handler
     */
    public static function init() {
        // Sync on package save
        add_action( 'tp_package_saved', array( __CLASS__, 'sync_package_to_product' ) );

        // Delete product on package delete
        add_action( 'tp_package_before_delete', array( __CLASS__, 'delete_product_for_package' ) );

        // Clean up when treatment is deleted
        add_action( 'before_delete_post', array( __CLASS__, 'on_treatment_delete' ) );

        // Hide synced products from shop
        add_action( 'woocommerce_product_query', array( __CLASS__, 'hide_from_shop' ) );

        // Hide from admin product list (optional - can be toggled)
        add_filter( 'pre_get_posts', array( __CLASS__, 'filter_admin_products' ) );
    }

    /**
     * Flag to prevent infinite loop during sync
     *
     * @var bool
     */
    private static $syncing = false;

    /**
     * Sync a package to a WooCommerce product
     *
     * @param PackageModel $package Package to sync.
     * @return int|false WooCommerce product ID or false on failure.
     */
    public static function sync_package_to_product( PackageModel $package ) {
        // Prevent infinite loop
        if ( self::$syncing ) {
            return false;
        }

        if ( ! function_exists( 'wc_get_product' ) ) {
            return false;
        }

        self::$syncing = true;

        $product_id = $package->get_wc_product_id();
        $product = null;

        // Try to get existing product
        if ( $product_id ) {
            $product = wc_get_product( $product_id );
        }

        // If no product exists, create new one
        if ( ! $product ) {
            $product = new \WC_Product_Simple();
        }

        // Get treatment details
        $treatment = $package->get_treatment();
        $treatment_title = $treatment ? $treatment->post_title : __( 'Treatment', 'treatment-packages-deposits' );

        // Build product name
        $product_name = sprintf(
            '%s - %s',
            $treatment_title,
            $package->get_display_name()
        );

        // Update product data
        $product->set_name( $product_name );
        $product->set_slug( sanitize_title( $product_name . '-' . $package->get_id() ) );
        $product->set_status( 'publish' );
        $product->set_catalog_visibility( 'hidden' ); // Hide from shop/search
        $product->set_price( $package->get_total_price() );
        $product->set_regular_price( $package->get_total_price() );
        $product->set_virtual( true );
        $product->set_sold_individually( false );
        $product->set_manage_stock( false );
        $product->set_stock_status( 'instock' );

        // Set description
        $description = sprintf(
            /* translators: 1: treatment name, 2: number of sessions, 3: price per session */
            __( '%1$s treatment package with %2$d session(s). Price per session: %3$s', 'treatment-packages-deposits' ),
            $treatment_title,
            $package->get_sessions(),
            $package->get_formatted_per_session_price()
        );
        $product->set_short_description( $description );

        // Set featured image from treatment if available
        if ( $treatment && has_post_thumbnail( $treatment->ID ) ) {
            $thumbnail_id = get_post_thumbnail_id( $treatment->ID );
            $product->set_image_id( $thumbnail_id );
        }

        // Save the product
        $product_id = $product->save();

        if ( ! $product_id ) {
            self::$syncing = false;
            return false;
        }

        // Store package and treatment references
        update_post_meta( $product_id, self::META_KEY_PACKAGE_ID, $package->get_id() );
        update_post_meta( $product_id, self::META_KEY_TREATMENT_ID, $package->get_treatment_id() );

        // Store deposit information as product meta
        update_post_meta( $product_id, '_tp_deposit_type', $package->get_deposit_type() );
        update_post_meta( $product_id, '_tp_deposit_value', $package->get_deposit_value() );
        update_post_meta( $product_id, '_tp_sessions', $package->get_sessions() );
        update_post_meta( $product_id, '_tp_total_price', $package->get_total_price() );

        // Update package with WC product ID if it changed
        if ( $package->get_wc_product_id() !== $product_id ) {
            $package->set_wc_product_id( $product_id );
            PackageRepository::save( $package );
        }

        /**
         * Action fired after a package is synced to a WooCommerce product
         *
         * @param int          $product_id WooCommerce product ID.
         * @param PackageModel $package    Package model.
         */
        do_action( 'tp_package_product_synced', $product_id, $package );

        self::$syncing = false;

        return $product_id;
    }

    /**
     * Delete the WooCommerce product for a package
     *
     * @param PackageModel $package Package being deleted.
     */
    public static function delete_product_for_package( PackageModel $package ) {
        $product_id = $package->get_wc_product_id();

        if ( ! $product_id ) {
            return;
        }

        // Verify this product belongs to the package
        $stored_package_id = get_post_meta( $product_id, self::META_KEY_PACKAGE_ID, true );

        if ( (int) $stored_package_id !== $package->get_id() ) {
            return;
        }

        // Delete the product (move to trash first, then delete permanently)
        wp_delete_post( $product_id, true );

        /**
         * Action fired after a package product is deleted
         *
         * @param int          $product_id Deleted product ID.
         * @param PackageModel $package    Package model.
         */
        do_action( 'tp_package_product_deleted', $product_id, $package );
    }

    /**
     * Handle treatment deletion - clean up all associated products
     *
     * @param int $post_id Post ID being deleted.
     */
    public static function on_treatment_delete( $post_id ) {
        if ( get_post_type( $post_id ) !== 'treatment' ) {
            return;
        }

        // Find all WC products linked to this treatment
        $products = self::get_products_by_treatment( $post_id );

        foreach ( $products as $product_id ) {
            wp_delete_post( $product_id, true );
        }
    }

    /**
     * Get all WooCommerce product IDs for a treatment
     *
     * @param int $treatment_id Treatment post ID.
     * @return array Array of product IDs.
     */
    public static function get_products_by_treatment( $treatment_id ) {
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'any',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => self::META_KEY_TREATMENT_ID,
                    'value' => $treatment_id,
                    'type'  => 'NUMERIC',
                ),
            ),
        );

        return get_posts( $args );
    }

    /**
     * Get WooCommerce product for a package
     *
     * @param int $package_id Package ID.
     * @return \WC_Product|null
     */
    public static function get_product_by_package( $package_id ) {
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => 1,
            'fields'         => 'ids',
            'meta_query'     => array(
                array(
                    'key'   => self::META_KEY_PACKAGE_ID,
                    'value' => $package_id,
                    'type'  => 'NUMERIC',
                ),
            ),
        );

        $products = get_posts( $args );

        if ( empty( $products ) ) {
            return null;
        }

        return wc_get_product( $products[0] );
    }

    /**
     * Hide synced products from shop/catalog pages
     *
     * @param \WP_Query $query WooCommerce product query.
     */
    public static function hide_from_shop( $query ) {
        if ( is_admin() ) {
            return;
        }

        $meta_query = $query->get( 'meta_query' ) ?: array();

        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key'     => self::META_KEY_PACKAGE_ID,
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'   => self::META_KEY_PACKAGE_ID,
                'value' => '',
            ),
        );

        $query->set( 'meta_query', $meta_query );
    }

    /**
     * Optionally hide synced products from admin product list
     *
     * @param \WP_Query $query Query object.
     */
    public static function filter_admin_products( $query ) {
        if ( ! is_admin() || ! $query->is_main_query() ) {
            return;
        }

        $screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;

        if ( ! $screen || 'edit-product' !== $screen->id ) {
            return;
        }

        // Check if we want to show treatment products
        $show_treatment_products = isset( $_GET['tp_show_packages'] ) && '1' === $_GET['tp_show_packages'];

        if ( $show_treatment_products ) {
            return;
        }

        // Hide treatment package products by default
        $meta_query = $query->get( 'meta_query' ) ?: array();

        $meta_query[] = array(
            'relation' => 'OR',
            array(
                'key'     => self::META_KEY_PACKAGE_ID,
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key'   => self::META_KEY_PACKAGE_ID,
                'value' => '',
            ),
        );

        $query->set( 'meta_query', $meta_query );
    }

    /**
     * Manually sync all packages for a treatment
     *
     * @param int $treatment_id Treatment post ID.
     * @return array Array of synced product IDs.
     */
    public static function sync_all_packages( $treatment_id ) {
        $packages = PackageRepository::get_by_treatment( $treatment_id );
        $product_ids = array();

        foreach ( $packages as $package ) {
            $product_id = self::sync_package_to_product( $package );
            if ( $product_id ) {
                $product_ids[] = $product_id;
            }
        }

        return $product_ids;
    }

    /**
     * Check if a WooCommerce product is a treatment package product
     *
     * @param int|\WC_Product $product Product ID or object.
     * @return bool
     */
    public static function is_package_product( $product ) {
        if ( is_numeric( $product ) ) {
            $product_id = $product;
        } elseif ( $product instanceof \WC_Product ) {
            $product_id = $product->get_id();
        } else {
            return false;
        }

        $package_id = get_post_meta( $product_id, self::META_KEY_PACKAGE_ID, true );

        return ! empty( $package_id );
    }

    /**
     * Get package data from a WooCommerce product
     *
     * @param int|\WC_Product $product Product ID or object.
     * @return PackageModel|null
     */
    public static function get_package_from_product( $product ) {
        if ( is_numeric( $product ) ) {
            $product_id = $product;
        } elseif ( $product instanceof \WC_Product ) {
            $product_id = $product->get_id();
        } else {
            return null;
        }

        $package_id = get_post_meta( $product_id, self::META_KEY_PACKAGE_ID, true );

        if ( ! $package_id ) {
            return null;
        }

        return PackageRepository::find( $package_id );
    }
}
