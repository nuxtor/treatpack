<?php
/**
 * Import Treatment Packages Dummy Data
 *
 * Usage:
 * 1. Place this file in the plugin folder
 * 2. Place dummy-data.json in the same folder
 * 3. Run from command line: php import-dummy-data.php
 * 4. Or visit: yoursite.com/wp-content/plugins/treatment-packages-deposits/import-dummy-data.php?run=1&key=YOUR_SECRET
 *
 * @package TreatmentPackages
 */

// Security key - change this before using!
define( 'IMPORT_SECRET_KEY', 'change-this-secret-key-2024' );

// Check if running from CLI or web
$is_cli = ( php_sapi_name() === 'cli' );

if ( ! $is_cli ) {
    // Web request - require secret key
    if ( ! isset( $_GET['run'] ) || ! isset( $_GET['key'] ) || $_GET['key'] !== IMPORT_SECRET_KEY ) {
        die( 'Access denied. Use: ?run=1&key=YOUR_SECRET_KEY' );
    }
}

// Find WordPress root
// __DIR__ = plugins/treatment-packages-deposits
// Go up: plugins -> wp-content -> wordpress root
$wp_root = dirname( dirname( dirname( __DIR__ ) ) );
$wp_load = $wp_root . '/wp-load.php';

// Try alternative paths if not found
if ( ! file_exists( $wp_load ) ) {
    // Try ABSPATH style paths
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
    }
}

output( "Starting import..." );

global $wpdb;

// Load JSON data
$json_file = __DIR__ . '/london-premier-laser-data.json';

if ( ! file_exists( $json_file ) ) {
    die( "dummy-data.json not found at: $json_file\n" );
}

$data = json_decode( file_get_contents( $json_file ), true );

if ( ! $data ) {
    die( "Failed to parse JSON file\n" );
}

output( "Loaded data from: " . $data['site_url'] );
output( "Generated at: " . $data['generated_at'] );

// Track ID mappings for relationships
$term_map = array();
$treatment_map = array();

// =============================================================================
// 1. Import Categories
// =============================================================================
output( "\n--- Importing Categories ---" );

foreach ( $data['categories'] as $cat ) {
    $existing = term_exists( $cat['slug'], 'treatment_category' );

    if ( $existing ) {
        $term_map['category'][ $cat['term_id'] ] = $existing['term_id'];
        output( "Category exists: " . $cat['name'] );
    } else {
        $result = wp_insert_term(
            $cat['name'],
            'treatment_category',
            array(
                'slug'        => $cat['slug'],
                'description' => $cat['description'],
            )
        );

        if ( ! is_wp_error( $result ) ) {
            $term_map['category'][ $cat['term_id'] ] = $result['term_id'];
            output( "Created category: " . $cat['name'] );
        } else {
            output( "Error creating category: " . $result->get_error_message() );
        }
    }
}

// =============================================================================
// 2. Import Areas
// =============================================================================
output( "\n--- Importing Treatment Areas ---" );

foreach ( $data['areas'] as $area ) {
    $existing = term_exists( $area['slug'], 'treatment_area' );

    if ( $existing ) {
        $term_map['area'][ $area['term_id'] ] = $existing['term_id'];
        output( "Area exists: " . $area['name'] );
    } else {
        $result = wp_insert_term(
            $area['name'],
            'treatment_area',
            array(
                'slug'        => $area['slug'],
                'description' => $area['description'],
            )
        );

        if ( ! is_wp_error( $result ) ) {
            $term_map['area'][ $area['term_id'] ] = $result['term_id'];
            output( "Created area: " . $area['name'] );
        } else {
            output( "Error creating area: " . $result->get_error_message() );
        }
    }
}

// =============================================================================
// 3. Import Treatments
// =============================================================================
output( "\n--- Importing Treatments ---" );

foreach ( $data['treatments'] as $treatment ) {
    // Check if treatment with same title exists
    $existing = get_page_by_title( html_entity_decode( $treatment['post_title'] ), OBJECT, 'treatment' );

    if ( $existing ) {
        $treatment_map[ $treatment['ID'] ] = $existing->ID;
        output( "Treatment exists: " . $treatment['post_title'] );
        continue;
    }

    // Create treatment
    $post_id = wp_insert_post( array(
        'post_type'    => 'treatment',
        'post_title'   => html_entity_decode( $treatment['post_title'] ),
        'post_content' => $treatment['post_content'],
        'post_excerpt' => $treatment['post_excerpt'],
        'post_status'  => $treatment['post_status'],
        'menu_order'   => $treatment['menu_order'],
    ) );

    if ( is_wp_error( $post_id ) ) {
        output( "Error creating treatment: " . $post_id->get_error_message() );
        continue;
    }

    $treatment_map[ $treatment['ID'] ] = $post_id;
    output( "Created treatment: " . $treatment['post_title'] . " (ID: $post_id)" );

    // Set categories
    if ( ! empty( $treatment['categories'] ) ) {
        wp_set_object_terms( $post_id, $treatment['categories'], 'treatment_category' );
    }

    // Set areas
    if ( ! empty( $treatment['areas'] ) ) {
        wp_set_object_terms( $post_id, $treatment['areas'], 'treatment_area' );
    }

    // Set meta
    if ( ! empty( $treatment['default_deposit_type'] ) ) {
        update_post_meta( $post_id, '_treatment_default_deposit_type', $treatment['default_deposit_type'] );
    }
    if ( ! empty( $treatment['default_deposit_value'] ) ) {
        update_post_meta( $post_id, '_treatment_default_deposit_value', $treatment['default_deposit_value'] );
    }

    // Import featured image if URL provided
    if ( ! empty( $treatment['featured_image_url'] ) ) {
        output( "  Note: Featured image URL stored but not downloaded: " . $treatment['featured_image_url'] );
        // You can implement image downloading here if needed
    }
}

// =============================================================================
// 4. Import Packages
// =============================================================================
output( "\n--- Importing Packages ---" );

foreach ( $data['packages'] as $package ) {
    $old_treatment_id = $package['treatment_id'];
    $new_treatment_id = isset( $treatment_map[ $old_treatment_id ] ) ? $treatment_map[ $old_treatment_id ] : null;

    if ( ! $new_treatment_id ) {
        output( "Skipping package - treatment ID $old_treatment_id not found" );
        continue;
    }

    // Check if package already exists
    $existing = $wpdb->get_var( $wpdb->prepare(
        "SELECT id FROM {$wpdb->prefix}tp_packages
         WHERE treatment_id = %d AND sessions = %d",
        $new_treatment_id,
        $package['sessions']
    ) );

    if ( $existing ) {
        output( "Package exists: " . $package['sessions'] . " sessions for treatment $new_treatment_id" );
        continue;
    }

    // Insert package
    $wpdb->insert(
        $wpdb->prefix . 'tp_packages',
        array(
            'treatment_id'      => $new_treatment_id,
            'name'              => $package['name'],
            'sessions'          => $package['sessions'],
            'total_price'       => $package['total_price'],
            'per_session_price' => $package['per_session_price'],
            'discount_percent'  => $package['discount_percent'],
            'deposit_type'      => $package['deposit_type'],
            'deposit_value'     => $package['deposit_value'],
            'sort_order'        => $package['sort_order'],
            'created_at'        => current_time( 'mysql' ),
            'updated_at'        => current_time( 'mysql' ),
        ),
        array( '%d', '%s', '%d', '%f', '%f', '%f', '%s', '%f', '%d', '%s', '%s' )
    );

    $new_package_id = $wpdb->insert_id;

    if ( $new_package_id ) {
        output( "Created package: " . $package['sessions'] . " sessions @ £" . $package['total_price'] . " (ID: $new_package_id)" );

        // Sync to WooCommerce product
        if ( class_exists( 'TreatmentPackages\\Packages\\PackageRepository' ) ) {
            $package_model = \TreatmentPackages\Packages\PackageRepository::find( $new_package_id );
            if ( $package_model ) {
                \TreatmentPackages\Woo\ProductsSync::sync_package_to_product( $package_model );
                output( "  -> Synced to WooCommerce" );
            }
        }
    } else {
        output( "Error creating package" );
    }
}

// =============================================================================
// Done
// =============================================================================
output( "\n=== Import Complete ===" );
output( "Categories imported: " . count( $data['categories'] ) );
output( "Areas imported: " . count( $data['areas'] ) );
output( "Treatments imported: " . count( $data['treatments'] ) );
output( "Packages imported: " . count( $data['packages'] ) );

// Clean up - delete this file after use
output( "\n⚠️  IMPORTANT: Delete this file after import for security!" );
