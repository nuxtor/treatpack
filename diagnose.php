<?php
/**
 * Diagnose Treatment Packages Data
 *
 * @package TreatmentPackages
 */

define( 'DIAG_SECRET_KEY', 'diag-secret-2024' );

$is_cli = ( php_sapi_name() === 'cli' );

if ( ! $is_cli ) {
    if ( ! isset( $_GET['run'] ) || ! isset( $_GET['key'] ) || $_GET['key'] !== DIAG_SECRET_KEY ) {
        die( 'Access denied. Use: ?run=1&key=diag-secret-2024' );
    }
}

// Find WordPress root
$wp_root = dirname( dirname( dirname( __DIR__ ) ) );
$wp_load = $wp_root . '/wp-load.php';

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
    die( "Could not find wp-load.php\n" );
}

require_once $wp_load;

echo "<h1>Treatment Packages Diagnostic Report</h1>";
echo "<style>body{font-family:monospace;} table{border-collapse:collapse;margin:20px 0;} th,td{border:1px solid #ccc;padding:8px;text-align:left;} th{background:#f5f5f5;}</style>";

global $wpdb;

// 1. Categories
echo "<h2>1. Treatment Categories</h2>";
$categories = get_terms( array(
    'taxonomy'   => 'treatment_category',
    'hide_empty' => false,
));

if ( is_wp_error( $categories ) ) {
    echo "<p style='color:red;'>Error: " . $categories->get_error_message() . "</p>";
} else {
    echo "<table><tr><th>ID</th><th>Name</th><th>Slug</th><th>Count</th></tr>";
    foreach ( $categories as $cat ) {
        echo "<tr><td>{$cat->term_id}</td><td>{$cat->name}</td><td>{$cat->slug}</td><td>{$cat->count}</td></tr>";
    }
    echo "</table>";
}

// 2. Treatments by Category
echo "<h2>2. Treatments by Category</h2>";

foreach ( $categories as $cat ) {
    $treatments = get_posts( array(
        'post_type'      => 'treatment',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'tax_query'      => array(
            array(
                'taxonomy' => 'treatment_category',
                'field'    => 'slug',
                'terms'    => $cat->slug,
            ),
        ),
    ));

    echo "<h3>{$cat->name} ({$cat->slug}) - " . count( $treatments ) . " treatments</h3>";

    if ( ! empty( $treatments ) ) {
        echo "<table><tr><th>ID</th><th>Title</th><th>Status</th><th>Packages</th><th>With WC Product</th></tr>";
        foreach ( $treatments as $treatment ) {
            $packages = $wpdb->get_results( $wpdb->prepare(
                "SELECT id, sessions, wc_product_id FROM {$wpdb->prefix}tp_packages WHERE treatment_id = %d",
                $treatment->ID
            ));

            $pkg_count = count( $packages );
            $with_wc = 0;
            foreach ( $packages as $pkg ) {
                if ( ! empty( $pkg->wc_product_id ) ) {
                    $with_wc++;
                }
            }

            echo "<tr><td>{$treatment->ID}</td><td>{$treatment->post_title}</td><td>{$treatment->post_status}</td><td>{$pkg_count}</td><td>{$with_wc}</td></tr>";
        }
        echo "</table>";
    } else {
        echo "<p><em>No treatments in this category</em></p>";
    }
}

// 3. Treatments without category
echo "<h2>3. Treatments WITHOUT any Category</h2>";
$all_treatments = get_posts( array(
    'post_type'      => 'treatment',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
));

$uncategorized = array();
foreach ( $all_treatments as $treatment ) {
    $terms = get_the_terms( $treatment->ID, 'treatment_category' );
    if ( empty( $terms ) || is_wp_error( $terms ) ) {
        $uncategorized[] = $treatment;
    }
}

if ( ! empty( $uncategorized ) ) {
    echo "<table><tr><th>ID</th><th>Title</th><th>Status</th></tr>";
    foreach ( $uncategorized as $treatment ) {
        echo "<tr><td>{$treatment->ID}</td><td>{$treatment->post_title}</td><td>{$treatment->post_status}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:green;'>All treatments have categories assigned!</p>";
}

// 4. Packages without WC Product
echo "<h2>4. Packages WITHOUT WooCommerce Product</h2>";
$packages_no_wc = $wpdb->get_results(
    "SELECT p.*, t.post_title as treatment_name
     FROM {$wpdb->prefix}tp_packages p
     LEFT JOIN {$wpdb->posts} t ON p.treatment_id = t.ID
     WHERE p.wc_product_id IS NULL OR p.wc_product_id = 0"
);

if ( ! empty( $packages_no_wc ) ) {
    echo "<p style='color:red;'>" . count( $packages_no_wc ) . " packages without WC products!</p>";
    echo "<table><tr><th>Package ID</th><th>Treatment</th><th>Sessions</th><th>Price</th></tr>";
    foreach ( $packages_no_wc as $pkg ) {
        echo "<tr><td>{$pkg->id}</td><td>{$pkg->treatment_name}</td><td>{$pkg->sessions}</td><td>£{$pkg->total_price}</td></tr>";
    }
    echo "</table>";
} else {
    echo "<p style='color:green;'>All packages have WooCommerce products!</p>";
}

// 5. Summary
echo "<h2>5. Summary</h2>";
$total_treatments = count( $all_treatments );
$total_packages = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tp_packages" );
$packages_with_wc = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->prefix}tp_packages WHERE wc_product_id IS NOT NULL AND wc_product_id > 0" );

echo "<ul>";
echo "<li>Total Categories: " . count( $categories ) . "</li>";
echo "<li>Total Treatments: {$total_treatments}</li>";
echo "<li>Total Packages: {$total_packages}</li>";
echo "<li>Packages with WC Products: {$packages_with_wc}</li>";
echo "<li>Uncategorized Treatments: " . count( $uncategorized ) . "</li>";
echo "</ul>";

echo "<br><strong style='color:red;'>DELETE THIS FILE AFTER USE!</strong>";
