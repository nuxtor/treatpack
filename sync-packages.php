<?php
/**
 * Sync All Packages to WooCommerce Products
 *
 * This script syncs all treatment packages to WooCommerce products.
 * Run this after importing data to create the WooCommerce products.
 *
 * Usage:
 * Visit: yoursite.com/wp-content/plugins/treatment-packages-deposits/sync-packages.php?run=1&key=sync-secret-2024
 *
 * @package TreatmentPackages
 */

// Security key
define( 'SYNC_SECRET_KEY', 'sync-secret-2024' );

// Check if running from CLI or web
$is_cli = ( php_sapi_name() === 'cli' );

if ( ! $is_cli ) {
    // Web request - require secret key
    if ( ! isset( $_GET['run'] ) || ! isset( $_GET['key'] ) || $_GET['key'] !== SYNC_SECRET_KEY ) {
        die( 'Access denied. Use: ?run=1&key=sync-secret-2024' );
    }
}

// Find WordPress root
$wp_root = dirname( dirname( dirname( __DIR__ ) ) );
$wp_load = $wp_root . '/wp-load.php';

// Try alternative paths if not found
if ( ! file_exists( $wp_load ) ) {
    $alternative_paths = array(
        dirname( dirname( dirname( __DIR__ ) ) ) . '/wp-load.php',
        dirname( dirname( dirname( dirname( __DIR__ ) ) ) ) . '/wp-load.php',
        $_SERVER['DOCUMENT_ROOT'] . '/wp-load.php',
    );

    foreach ( $alternative_paths as $path ) {
        if ( file_exists( $path ) ) {
            $wp_load = $path;
            break;
        }
    }
}

if ( ! file_exists( $wp_load ) ) {
    die( "Could not find wp-load.php. Tried: $wp_load\nCurrent dir: " . __DIR__ . "\n" );
}

require_once $wp_load;

// Output function
function output( $msg ) {
    global $is_cli;
    if ( $is_cli ) {
        echo $msg . "\n";
    } else {
        echo $msg . "<br>\n";
        flush();
        ob_flush();
    }
}

// Check WooCommerce
if ( ! class_exists( 'WooCommerce' ) ) {
    die( "WooCommerce is not active!\n" );
}

// Check plugin classes
if ( ! class_exists( 'TreatmentPackages\\Packages\\PackageRepository' ) ) {
    die( "Treatment Packages plugin is not active!\n" );
}

output( "<h2>Syncing Packages to WooCommerce Products</h2>" );
output( "Started at: " . current_time( 'mysql' ) );

global $wpdb;

// Get all packages
$packages = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}tp_packages ORDER BY treatment_id, sort_order" );

output( "<br>Found " . count( $packages ) . " packages to sync<br>" );

$synced = 0;
$errors = 0;
$skipped = 0;

foreach ( $packages as $pkg ) {
    // Create package model
    $package = \TreatmentPackages\Packages\PackageRepository::find( $pkg->id );

    if ( ! $package ) {
        output( "ERROR: Could not load package ID {$pkg->id}" );
        $errors++;
        continue;
    }

    // Check if already has WC product
    $existing_product_id = $package->get_wc_product_id();
    if ( $existing_product_id && wc_get_product( $existing_product_id ) ) {
        output( "SKIP: Package {$pkg->id} already has product {$existing_product_id}" );
        $skipped++;
        continue;
    }

    // Get treatment name
    $treatment = get_post( $pkg->treatment_id );
    $treatment_name = $treatment ? $treatment->post_title : "Treatment {$pkg->treatment_id}";

    // Sync to WooCommerce
    $product_id = \TreatmentPackages\Woo\ProductsSync::sync_package_to_product( $package );

    if ( $product_id ) {
        output( "OK: '{$treatment_name}' - {$package->get_display_name()} => Product #{$product_id}" );
        $synced++;
    } else {
        output( "ERROR: Failed to sync package {$pkg->id} for '{$treatment_name}'" );
        $errors++;
    }
}

output( "<br><h3>=== Sync Complete ===</h3>" );
output( "Synced: {$synced}" );
output( "Skipped (already synced): {$skipped}" );
output( "Errors: {$errors}" );
output( "<br>Finished at: " . current_time( 'mysql' ) );

output( "<br><strong style='color:red;'>IMPORTANT: Delete this file after use for security!</strong>" );
