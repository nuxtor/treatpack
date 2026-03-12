<?php
/**
 * Uninstall TreatPack - Treatment Packages for WooCommerce
 *
 * This file runs when the plugin is deleted from WordPress.
 * It removes all plugin data including database tables, options, and post meta.
 *
 * @package TreatPack
 */

// If uninstall.php is not called by WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

/**
 * Check if we should delete all data.
 * You can set this option to 'no' in wp_options to preserve data on uninstall.
 * Default is 'yes' - delete all data.
 */
$delete_data = get_option( 'tp_deposits_delete_data_on_uninstall', 'yes' );

if ( 'yes' !== $delete_data ) {
    return;
}

// =============================================================================
// 1. Delete Custom Database Tables
// =============================================================================

$tables = array(
    $wpdb->prefix . 'tp_packages',
    $wpdb->prefix . 'tp_customer_packages',
);

foreach ( $tables as $table ) {
    $wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
}

// =============================================================================
// 2. Delete WooCommerce Products Created by This Plugin
// =============================================================================

// Find all products that were synced from treatment packages
$synced_products = $wpdb->get_col(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_tp_package_id'"
);

if ( ! empty( $synced_products ) ) {
    foreach ( $synced_products as $product_id ) {
        wp_delete_post( $product_id, true );
    }
}

// =============================================================================
// 3. Delete Treatment Posts and Their Meta
// =============================================================================

// Get all treatment posts
$treatments = get_posts(
    array(
        'post_type'      => 'treatment',
        'post_status'    => 'any',
        'posts_per_page' => -1,
        'fields'         => 'ids',
    )
);

if ( ! empty( $treatments ) ) {
    foreach ( $treatments as $treatment_id ) {
        wp_delete_post( $treatment_id, true );
    }
}

// =============================================================================
// 4. Delete Custom Taxonomies Terms
// =============================================================================

$taxonomies = array( 'treatment_category', 'treatment_area' );

foreach ( $taxonomies as $taxonomy ) {
    $terms = get_terms(
        array(
            'taxonomy'   => $taxonomy,
            'hide_empty' => false,
            'fields'     => 'ids',
        )
    );

    if ( ! is_wp_error( $terms ) && ! empty( $terms ) ) {
        foreach ( $terms as $term_id ) {
            wp_delete_term( $term_id, $taxonomy );
        }
    }
}

// =============================================================================
// 5. Delete Plugin Options
// =============================================================================

$options = array(
    'tp_deposits_version',
    'tp_deposits_db_version',
    'tp_deposits_delete_data_on_uninstall',
    'tp_deposits_settings',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// =============================================================================
// 6. Delete Post Meta Related to Plugin
// =============================================================================

$meta_keys = array(
    '_tp_package_id',
    '_tp_treatment_id',
    '_tp_deposit_type',
    '_tp_deposit_value',
    '_tp_sessions',
    '_tp_total_price',
    '_tp_treatment_name',
    '_tp_package_name',
    '_tp_deposit_amount',
    '_tp_remaining_balance',
    '_tp_has_deposit',
    '_tp_sessions_used',
    '_tp_sessions_remaining',
    '_tp_amount_paid',
    '_treatment_default_deposit_type',
    '_treatment_default_deposit_value',
);

foreach ( $meta_keys as $meta_key ) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
            $meta_key
        )
    );
}

// Delete from order item meta as well
foreach ( $meta_keys as $meta_key ) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->prefix}woocommerce_order_itemmeta WHERE meta_key = %s",
            $meta_key
        )
    );
}

// =============================================================================
// 7. Delete User Meta Related to Plugin
// =============================================================================

$user_meta_keys = array(
    'tp_customer_packages',
);

foreach ( $user_meta_keys as $meta_key ) {
    $wpdb->query(
        $wpdb->prepare(
            "DELETE FROM {$wpdb->usermeta} WHERE meta_key = %s",
            $meta_key
        )
    );
}

// =============================================================================
// 8. Clear Any Transients
// =============================================================================

$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_tp_deposits_%'"
);

$wpdb->query(
    "DELETE FROM {$wpdb->options} WHERE option_name LIKE '%_transient_timeout_tp_deposits_%'"
);

// =============================================================================
// 9. Flush Rewrite Rules
// =============================================================================

// Note: flush_rewrite_rules() may not work in uninstall context
// The rules will be regenerated on next page load anyway
delete_option( 'rewrite_rules' );
