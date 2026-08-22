<?php
/**
 * TreatPack Uninstall
 *
 * Runs when the plugin is deleted via WordPress admin
 */

defined('WP_UNINSTALL_PLUGIN') || exit;

// Load plugin file for constants and autoloader
require_once __DIR__ . '/treatpack.php';

// Drop custom tables
\TreatmentPackages\DB\Installer::drop_tables();

// Delete options
delete_option('treatpack_db_version');
delete_option('treatpack_license_key');
delete_option('treatpack_license_status');

// Delete treatment posts and their meta
$treatments = get_posts([
    'post_type' => 'tp_treatment',
    'numberposts' => -1,
    'post_status' => 'any',
    'fields' => 'ids',
]);

foreach ($treatments as $treatment_id) {
    wp_delete_post($treatment_id, true);
}

// Delete hidden WooCommerce products created by the plugin
$products = get_posts([
    'post_type' => 'product',
    'numberposts' => -1,
    'post_status' => 'any',
    'fields' => 'ids',
    'meta_query' => [
        [
            'key' => '_treatpack_package_id',
            'compare' => 'EXISTS',
        ],
    ],
]);

foreach ($products as $product_id) {
    wp_delete_post($product_id, true);
}

// Clear any transients
delete_transient('treatpack_license_check');

// Flush rewrite rules
flush_rewrite_rules();
