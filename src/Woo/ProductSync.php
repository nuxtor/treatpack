<?php

namespace TreatmentPackages\Woo;

use TreatmentPackages\Packages\PackageModel;
use TreatmentPackages\Packages\PackageRepository;

defined('ABSPATH') || exit;

/**
 * WooCommerce Product Sync
 *
 * Syncs treatment packages to hidden WooCommerce products
 */
class ProductSync {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('treatpack_package_saved', [$this, 'sync_package_to_product']);
        add_action('treatpack_before_package_delete', [$this, 'delete_synced_product']);
        add_action('before_delete_post', [$this, 'on_treatment_delete']);
    }

    /**
     * Sync package to WooCommerce product
     *
     * @param PackageModel $package Package model
     */
    public function sync_package_to_product(PackageModel $package): void {
        $product_id = $package->get_product_id();

        if ($product_id) {
            $product = wc_get_product($product_id);
            if (!$product) {
                $product_id = null;
            }
        }

        // Create or update product
        if ($product_id) {
            $product = wc_get_product($product_id);
        } else {
            $product = new \WC_Product_Simple();
        }

        $treatment = get_post($package->get_treatment_id());
        $treatment_name = $treatment ? $treatment->post_title : __('Treatment', 'treatpack');

        // Set product data
        $product->set_name(sprintf('%s - %s', $treatment_name, $package->get_name()));
        $product->set_status($package->is_active() ? 'publish' : 'draft');
        $product->set_catalog_visibility('hidden');
        $product->set_price($package->get_active_price());
        $product->set_regular_price($package->get_price());

        if ($package->get_sale_price()) {
            $product->set_sale_price($package->get_sale_price());
        } else {
            $product->set_sale_price('');
        }

        $product->set_virtual(true);
        $product->set_sold_individually(true);
        $product->set_manage_stock(false);

        // Set description
        $description = $package->get_description() ?: '';
        $description .= sprintf(
            "\n\n%s: %d\n%s: %s",
            __('Sessions', 'treatpack'),
            $package->get_sessions(),
            __('Treatment', 'treatpack'),
            $treatment_name
        );
        $product->set_description($description);

        // Save product
        $saved_product_id = $product->save();

        // Store meta references
        update_post_meta($saved_product_id, '_treatpack_package_id', $package->get_id());
        update_post_meta($saved_product_id, '_treatpack_treatment_id', $package->get_treatment_id());

        // Update package with product ID if new
        if (!$product_id && $saved_product_id) {
            global $wpdb;
            $wpdb->update(
                \TreatmentPackages\DB\Installer::get_packages_table(),
                ['product_id' => $saved_product_id],
                ['id' => $package->get_id()],
                ['%d'],
                ['%d']
            );
        }
    }

    /**
     * Delete synced product when package is deleted
     *
     * @param PackageModel $package Package model
     */
    public function delete_synced_product(PackageModel $package): void {
        $product_id = $package->get_product_id();

        if ($product_id) {
            wp_delete_post($product_id, true);
        }
    }

    /**
     * Handle treatment deletion
     *
     * @param int $post_id Post ID
     */
    public function on_treatment_delete(int $post_id): void {
        if (get_post_type($post_id) !== 'tp_treatment') {
            return;
        }

        // Get all packages for this treatment
        $packages = PackageRepository::get_by_treatment($post_id, false);

        foreach ($packages as $package) {
            $this->delete_synced_product($package);
        }

        // Delete all packages
        PackageRepository::delete_by_treatment($post_id);
    }

    /**
     * Get package ID from product
     *
     * @param int $product_id WooCommerce product ID
     * @return int|null
     */
    public static function get_package_id_from_product(int $product_id): ?int {
        $package_id = get_post_meta($product_id, '_treatpack_package_id', true);
        return $package_id ? (int) $package_id : null;
    }

    /**
     * Check if product is a TreatPack product
     *
     * @param int $product_id WooCommerce product ID
     * @return bool
     */
    public static function is_treatpack_product(int $product_id): bool {
        return (bool) get_post_meta($product_id, '_treatpack_package_id', true);
    }
}
