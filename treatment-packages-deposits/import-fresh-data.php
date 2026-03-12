<?php
/**
 * Fresh Data Import - Treatment Packages
 *
 * Removes ALL existing treatment data and imports fresh dataset.
 *
 * Usage via WP-CLI:
 *   wp eval-file wp-content/plugins/treatment-packages-deposits/import-fresh-data.php
 *
 * Or via browser:
 *   yoursite.com/wp-content/plugins/treatment-packages-deposits/import-fresh-data.php?run=1&key=fresh-import-2026
 *
 * @package TreatmentPackages
 */

// Security key for web access.
define( 'IMPORT_SECRET_KEY', 'fresh-import-2026' );

$is_cli = ( php_sapi_name() === 'cli' );

if ( ! $is_cli ) {
    if ( ! isset( $_GET['run'], $_GET['key'] ) || $_GET['key'] !== IMPORT_SECRET_KEY ) {
        die( 'Access denied. Use: ?run=1&key=YOUR_SECRET_KEY' );
    }
    echo '<pre>';
}

// Load WordPress.
$wp_root = dirname( dirname( dirname( __DIR__ ) ) );
$wp_load = $wp_root . '/wp-load.php';

if ( ! file_exists( $wp_load ) ) {
    die( "Could not find wp-load.php at: $wp_load\n" );
}

require_once $wp_load;

function output( $msg ) {
    echo $msg . "\n";
    if ( ob_get_level() ) {
        ob_flush();
    }
    flush();
}

output( '=== Fresh Data Import ===' );
output( 'Started: ' . current_time( 'mysql' ) );

global $wpdb;

// =============================================================================
// STEP 1: REMOVE ALL EXISTING DATA
// =============================================================================
output( "\n--- Step 1: Removing existing data ---" );

// 1a. Delete all WooCommerce products linked to packages.
$wc_products = get_posts( array(
    'post_type'      => 'product',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
    'meta_query'     => array(
        array(
            'key'     => '_tp_package_id',
            'compare' => 'EXISTS',
        ),
    ),
) );

$deleted_products = 0;
foreach ( $wc_products as $product_id ) {
    wp_delete_post( $product_id, true );
    $deleted_products++;
}
output( "Deleted $deleted_products WooCommerce package products." );

// 1b. Delete all treatment posts.
$treatments = get_posts( array(
    'post_type'      => 'treatment',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'fields'         => 'ids',
) );

$deleted_treatments = 0;
foreach ( $treatments as $treatment_id ) {
    wp_delete_post( $treatment_id, true );
    $deleted_treatments++;
}
output( "Deleted $deleted_treatments treatment posts." );

// 1c. Truncate packages table.
$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}tp_packages" );
output( "Truncated tp_packages table." );

// 1d. Truncate customer packages table.
$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}tp_customer_packages" );
output( "Truncated tp_customer_packages table." );

// 1e. Remove treatment taxonomy terms.
foreach ( array( 'treatment_category', 'treatment_area' ) as $taxonomy ) {
    $terms = get_terms( array(
        'taxonomy'   => $taxonomy,
        'hide_empty' => false,
        'fields'     => 'ids',
    ) );
    if ( ! is_wp_error( $terms ) ) {
        foreach ( $terms as $term_id ) {
            wp_delete_term( $term_id, $taxonomy );
        }
    }
}
output( "Removed taxonomy terms." );

output( "All existing data removed." );

// =============================================================================
// STEP 2: CREATE CATEGORIES
// =============================================================================
output( "\n--- Step 2: Creating categories ---" );

$women_cat = wp_insert_term( 'Women\'s Packages', 'treatment_category', array(
    'slug'        => 'women-packages',
    'description' => 'Laser hair removal packages for women.',
) );
$women_cat_id = is_wp_error( $women_cat ) ? 0 : $women_cat['term_id'];
output( "Created category: Women's Packages (ID: $women_cat_id)" );

$men_cat = wp_insert_term( 'Men\'s Packages', 'treatment_category', array(
    'slug'        => 'men-packages',
    'description' => 'Laser hair removal packages for men.',
) );
$men_cat_id = is_wp_error( $men_cat ) ? 0 : $men_cat['term_id'];
output( "Created category: Men's Packages (ID: $men_cat_id)" );

// =============================================================================
// STEP 3: DEFINE TREATMENTS & PACKAGES DATA
// =============================================================================

$treatments_data = array(
    // Women's treatments.
    array(
        'name'     => 'Upper Lip',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 25,    'per_session' => 25,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 112.5,  'per_session' => 18.75,  'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 130,    'per_session' => 16.25,  'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Chin',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 30,    'per_session' => 30,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 135,    'per_session' => 22.5,   'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 156,    'per_session' => 19.5,   'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Sides of Face',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 40,    'per_session' => 40,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 180,    'per_session' => 30,     'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 208,    'per_session' => 26,     'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Full Face',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 65,    'per_session' => 65,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 292.5,  'per_session' => 48.75,  'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 338,    'per_session' => 42.25,  'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Neck',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 35,    'per_session' => 35,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 157.5,  'per_session' => 26.25,  'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 182,    'per_session' => 22.75,  'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Underarms',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 35,    'per_session' => 35,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 157.5,  'per_session' => 26.25,  'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 182,    'per_session' => 22.75,  'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Half Arms',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 50,    'per_session' => 50,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 225,    'per_session' => 37.5,   'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 260,    'per_session' => 32.5,   'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Full Arms',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 85,    'per_session' => 85,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 382.5,  'per_session' => 63.75,  'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 442,    'per_session' => 55.25,  'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Hands & Fingers',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 20,    'per_session' => 20,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 90,     'per_session' => 15,     'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 104,    'per_session' => 13,     'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Bikini Line',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 40,    'per_session' => 40,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 180,    'per_session' => 30,     'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 208,    'per_session' => 26,     'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Extended Bikini',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 50,    'per_session' => 50,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 225,    'per_session' => 37.5,   'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 260,    'per_session' => 32.5,   'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Brazilian',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 55,    'per_session' => 55,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 247.5,  'per_session' => 41.25,  'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 286,    'per_session' => 35.75,  'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Hollywood',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 60,    'per_session' => 60,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 270,    'per_session' => 45,     'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 312,    'per_session' => 39,     'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Perianal',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 30,    'per_session' => 30,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 135,    'per_session' => 22.5,   'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 156,    'per_session' => 19.5,   'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Buttocks',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 55,    'per_session' => 55,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 247.5,  'per_session' => 41.25,  'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 286,    'per_session' => 35.75,  'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Half Legs',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 75,    'per_session' => 75,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 337.5,  'per_session' => 56.25,  'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 390,    'per_session' => 48.75,  'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Full Legs',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 130,   'per_session' => 130,    'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 585,    'per_session' => 97.5,   'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 676,    'per_session' => 84.5,   'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Feet & Toes',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 20,    'per_session' => 20,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 90,     'per_session' => 15,     'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 104,    'per_session' => 13,     'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Stomach / Abs',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 55,    'per_session' => 55,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 247.5,  'per_session' => 41.25,  'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 286,    'per_session' => 35.75,  'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Lower Back',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 45,    'per_session' => 45,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 202.5,  'per_session' => 33.75,  'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 234,    'per_session' => 29.25,  'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    // Combo packages (Women's).
    array(
        'name'     => 'Brazilian & Underarms',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 90,    'per_session' => 90,     'discount' => 0,      'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => '',                'sessions' => 5, 'total_price' => 250,    'per_session' => 50,     'discount' => 44.44,  'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 324,    'per_session' => 54,     'discount' => 40,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 360,    'per_session' => 45,     'discount' => 50,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 3 ),
        ),
    ),
    array(
        'name'     => 'Face & Neck',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 100,   'per_session' => 100,    'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 360,    'per_session' => 60,     'discount' => 40,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 400,    'per_session' => 50,     'discount' => 50,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Essential Full Body (Excl. Face & Neck)',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 250,   'per_session' => 250,    'discount' => 0,      'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 892.5,  'per_session' => 148.75, 'discount' => 40.5,   'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 1000,   'per_session' => 125,    'discount' => 50,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Ultimate Full Body',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 425,   'per_session' => 425,    'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 1530,   'per_session' => 255,    'discount' => 40,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 1700,   'per_session' => 212.5,  'discount' => 50,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Smooth Legs & Arms',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 215,   'per_session' => 215,    'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 774,    'per_session' => 129,    'discount' => 40,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 860,    'per_session' => 107.5,  'discount' => 50,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),

    // Men's treatments.
    array(
        'name'     => 'Men\'s Full Face',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 85,    'per_session' => 85,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 382.5,  'per_session' => 63.75,  'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 442,    'per_session' => 55.25,  'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Neck',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 45,    'per_session' => 45,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 202.5,  'per_session' => 33.75,  'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 234,    'per_session' => 29.25,  'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Shoulders',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 55,    'per_session' => 55,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 247.5,  'per_session' => 41.25,  'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 286,    'per_session' => 35.75,  'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Chest',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 75,    'per_session' => 75,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 337.5,  'per_session' => 56.25,  'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 390,    'per_session' => 48.75,  'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Abs',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 65,    'per_session' => 65,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 292.5,  'per_session' => 48.75,  'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 338,    'per_session' => 42.25,  'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Full Back',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 120,   'per_session' => 120,    'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 540,    'per_session' => 90,     'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 624,    'per_session' => 78,     'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Underarms',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 40,    'per_session' => 40,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 180,    'per_session' => 30,     'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 208,    'per_session' => 26,     'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Full Arms',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 110,   'per_session' => 110,    'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 495,    'per_session' => 82.5,   'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 572,    'per_session' => 71.5,   'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Bikini Line',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 45,    'per_session' => 45,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 202.5,  'per_session' => 33.75,  'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 234,    'per_session' => 29.25,  'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Brazilian',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 60,    'per_session' => 60,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 270,    'per_session' => 45,     'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 312,    'per_session' => 39,     'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Hollywood',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 65,    'per_session' => 65,     'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 292.5,  'per_session' => 48.75,  'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 338,    'per_session' => 42.25,  'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Full Legs',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 150,   'per_session' => 150,    'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 675,    'per_session' => 112.5,  'discount' => 25,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 780,    'per_session' => 97.5,   'discount' => 35,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    // Men's combo packages.
    array(
        'name'     => 'Men\'s Essential Full Body',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 350,   'per_session' => 350,    'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 1260,   'per_session' => 210,    'discount' => 40,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 1400,   'per_session' => 175,    'discount' => 50,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Ultimate Full Body',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 550,   'per_session' => 550,    'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 1980,   'per_session' => 330,    'discount' => 40,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 2200,   'per_session' => 275,    'discount' => 50,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Chest & Back',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 195,   'per_session' => 195,    'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 702,    'per_session' => 117,    'discount' => 40,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 780,    'per_session' => 97.5,   'discount' => 50,    'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),

    // =========================================================================
    // Women's — Upper Body
    // =========================================================================
    array(
        'name'     => 'Nipple',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 23.09,  'per_session' => 23.09,  'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 125,     'per_session' => 20.83,  'discount' => 9.78,  'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 161.66,  'per_session' => 20.21,  'discount' => 12.48, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Naval Line',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 32.35,  'per_session' => 32.35,  'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 174,     'per_session' => 29.00,  'discount' => 10.36, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 207,     'per_session' => 25.88,  'discount' => 20.02, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Full Back',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 98.5,   'per_session' => 98.50,  'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 531,     'per_session' => 88.50,  'discount' => 10.15, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 630,     'per_session' => 78.75,  'discount' => 20.05, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Shoulder',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 63.72,  'per_session' => 63.72,  'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 344,     'per_session' => 57.33,  'discount' => 10.03, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 407,     'per_session' => 50.88,  'discount' => 20.16, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Half Face',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 44.5,   'per_session' => 44.50,  'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 240,     'per_session' => 40.00,  'discount' => 10.11, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 284,     'per_session' => 35.50,  'discount' => 20.22, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),

    // =========================================================================
    // Women's — Lower Body
    // =========================================================================
    array(
        'name'     => 'Brazilian inc Perianal',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 42.5,   'per_session' => 42.50,  'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 229,     'per_session' => 38.17,  'discount' => 10.20, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 272,     'per_session' => 34.00,  'discount' => 20.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Hollywood inc Perianal',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 49.69,  'per_session' => 49.69,  'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 268,     'per_session' => 44.67,  'discount' => 10.11, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 318,     'per_session' => 39.75,  'discount' => 20.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Any Bikini and Underarm and Perianal',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 57.08,  'per_session' => 57.08,  'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 274,     'per_session' => 45.67,  'discount' => 20.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 319,     'per_session' => 39.88,  'discount' => 30.14, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),

    // =========================================================================
    // Women's — Combo Packages
    // =========================================================================
    array(
        'name'     => 'Half Leg and Any Bikini and Underarm',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 111.84, 'per_session' => 111.84, 'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 603.9,   'per_session' => 100.65, 'discount' => 10.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 715,     'per_session' => 89.38,  'discount' => 20.08, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Full Legs and Any Bikini and Perianal and Underarm',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 142.28, 'per_session' => 142.28, 'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 682,     'per_session' => 113.67, 'discount' => 20.11, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 796.74,  'per_session' => 99.59,  'discount' => 30.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Half Arms and Any Bikini and Underarm',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 111.84, 'per_session' => 111.84, 'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 608,     'per_session' => 101.33, 'discount' => 9.40,  'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 715,     'per_session' => 89.38,  'discount' => 20.08, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Upper Lip and Chin',
        'category' => 'women',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 32.5,   'per_session' => 32.50,  'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 156,     'per_session' => 26.00,  'discount' => 20.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 182,     'per_session' => 22.75,  'discount' => 30.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),

    // =========================================================================
    // Men's — New Packages
    // =========================================================================
    array(
        'name'     => 'Men\'s Sculpting Beard',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 42.88,  'per_session' => 42.88,  'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 231.55,  'per_session' => 38.59,  'discount' => 10.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 274,     'per_session' => 34.25,  'discount' => 20.13, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Cheeks',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 41.99,  'per_session' => 41.99,  'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 226,     'per_session' => 37.67,  'discount' => 10.29, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 268,     'per_session' => 33.50,  'discount' => 20.22, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Chest and Shoulder',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 99.5,   'per_session' => 99.50,  'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 477.6,   'per_session' => 79.60,  'discount' => 20.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 557.2,   'per_session' => 69.65,  'discount' => 30.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Torso, Back, Shoulder, Arm, Abs and Chest',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 144.75, 'per_session' => 144.75, 'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 781.65,  'per_session' => 130.28, 'discount' => 10.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 926.4,   'per_session' => 115.80, 'discount' => 20.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Half Body (Excl. Face, Neck, Chest, Abs, Shoulder & Back)',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 212.5,  'per_session' => 212.50, 'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 899,     'per_session' => 149.83, 'discount' => 29.49, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 1360,    'per_session' => 170.00, 'discount' => 20.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Ultimate Full Body (Excl. Face & Neck)',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 271,    'per_session' => 271.00, 'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 1199,    'per_session' => 199.83, 'discount' => 26.26, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 1734.4,  'per_session' => 216.80, 'discount' => 20.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Any Bikini inc Perianal',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 75.58,  'per_session' => 75.58,  'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 408.12,  'per_session' => 68.02,  'discount' => 10.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 483.7,   'per_session' => 60.46,  'discount' => 20.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Full Back and Shoulders',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 101.83, 'per_session' => 101.83, 'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 549.9,   'per_session' => 91.65,  'discount' => 10.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 651.73,  'per_session' => 81.47,  'discount' => 20.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
    array(
        'name'     => 'Men\'s Chest and Abdomen',
        'category' => 'men',
        'packages' => array(
            array( 'name' => 'Single Session', 'sessions' => 1, 'total_price' => 92.57,  'per_session' => 92.57,  'discount' => 0,     'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 0 ),
            array( 'name' => 'Course of 6',    'sessions' => 6, 'total_price' => 549.9,   'per_session' => 91.65,  'discount' => 0.99,  'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 1 ),
            array( 'name' => 'Course of 8',    'sessions' => 8, 'total_price' => 651.73,  'per_session' => 81.47,  'discount' => 12.00, 'deposit_type' => 'fixed', 'deposit_value' => 10, 'sort' => 2 ),
        ),
    ),
);

// =============================================================================
// STEP 4: CREATE TREATMENTS & PACKAGES
// =============================================================================
output( "\n--- Step 3: Creating treatments & packages ---" );

$total_treatments = 0;
$total_packages   = 0;

foreach ( $treatments_data as $treatment_data ) {
    // Create treatment post.
    $post_id = wp_insert_post( array(
        'post_type'   => 'treatment',
        'post_title'  => $treatment_data['name'],
        'post_status' => 'publish',
    ) );

    if ( is_wp_error( $post_id ) ) {
        output( "ERROR creating treatment: " . $treatment_data['name'] . ' - ' . $post_id->get_error_message() );
        continue;
    }

    $total_treatments++;

    // Assign category.
    $cat_id = ( 'men' === $treatment_data['category'] ) ? $men_cat_id : $women_cat_id;
    if ( $cat_id ) {
        wp_set_object_terms( $post_id, array( (int) $cat_id ), 'treatment_category' );
    }

    // Set default deposit meta.
    update_post_meta( $post_id, '_treatment_default_deposit_type', 'fixed' );
    update_post_meta( $post_id, '_treatment_default_deposit_value', 10 );

    output( "Treatment: {$treatment_data['name']} (ID: $post_id)" );

    // Create packages for this treatment.
    foreach ( $treatment_data['packages'] as $pkg ) {
        $wpdb->insert(
            $wpdb->prefix . 'tp_packages',
            array(
                'treatment_id'      => $post_id,
                'name'              => $pkg['name'],
                'sessions'          => $pkg['sessions'],
                'total_price'       => $pkg['total_price'],
                'per_session_price' => $pkg['per_session'],
                'discount_percent'  => $pkg['discount'],
                'deposit_type'      => $pkg['deposit_type'],
                'deposit_value'     => $pkg['deposit_value'],
                'sort_order'        => $pkg['sort'],
                'created_at'        => current_time( 'mysql' ),
                'updated_at'        => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%d', '%f', '%f', '%f', '%s', '%f', '%d', '%s', '%s' )
        );

        $new_pkg_id = $wpdb->insert_id;

        if ( ! $new_pkg_id ) {
            output( "  ERROR inserting package: {$pkg['sessions']} sessions" );
            continue;
        }

        $total_packages++;

        // Sync to WooCommerce product.
        if ( class_exists( 'TreatmentPackages\\Packages\\PackageRepository' ) ) {
            $package_model = \TreatmentPackages\Packages\PackageRepository::find( $new_pkg_id );
            if ( $package_model ) {
                $product_id = \TreatmentPackages\Woo\ProductsSync::sync_package_to_product( $package_model );
                $label = $pkg['name'] ?: "{$pkg['sessions']} sessions";
                output( "  Package: $label @ £{$pkg['total_price']} -> WC Product #$product_id" );
            }
        } else {
            $label = $pkg['name'] ?: "{$pkg['sessions']} sessions";
            output( "  Package: $label @ £{$pkg['total_price']} (WC sync unavailable)" );
        }
    }
}

// =============================================================================
// DONE
// =============================================================================
output( "\n=== Import Complete ===" );
output( "Treatments created: $total_treatments" );
output( "Packages created: $total_packages" );
output( 'Finished: ' . current_time( 'mysql' ) );
output( "\nIMPORTANT: Delete this file after import for security!" );

if ( ! $is_cli ) {
    echo '</pre>';
}
