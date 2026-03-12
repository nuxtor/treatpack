<?php
/**
 * Fix Categories - Rename and Reassign Treatments
 *
 * @package TreatmentPackages
 */

define( 'FIX_SECRET_KEY', 'fix-cat-2024' );

$is_cli = ( php_sapi_name() === 'cli' );

if ( ! $is_cli ) {
    if ( ! isset( $_GET['run'] ) || ! isset( $_GET['key'] ) || $_GET['key'] !== FIX_SECRET_KEY ) {
        die( 'Access denied. Use: ?run=1&key=fix-cat-2024' );
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

function output( $msg ) {
    echo $msg . "<br>\n";
    flush();
    @ob_flush();
}

echo "<h1>Fixing Categories</h1>";

// =============================================================================
// Step 1: Create/Update the 5 categories
// =============================================================================
output( "<h2>Step 1: Creating Categories</h2>" );

$new_categories = array(
    array(
        'name' => 'Women Packages',
        'slug' => 'women-packages',
        'description' => 'Multi-area package deals for women',
    ),
    array(
        'name' => 'Individual Lower Body',
        'slug' => 'individual-lower-body',
        'description' => 'Individual laser hair removal treatments for lower body areas',
    ),
    array(
        'name' => 'Individual Upper Body',
        'slug' => 'individual-upper-body',
        'description' => 'Individual laser hair removal treatments for upper body areas',
    ),
    array(
        'name' => 'Individual Face Area',
        'slug' => 'individual-face-area',
        'description' => 'Individual laser hair removal treatments for face and neck',
    ),
    array(
        'name' => 'Men Packages',
        'slug' => 'men-packages',
        'description' => 'Laser hair removal treatments and packages for men',
    ),
);

$category_ids = array();

foreach ( $new_categories as $cat ) {
    $existing = term_exists( $cat['slug'], 'treatment_category' );

    if ( $existing ) {
        // Update existing
        wp_update_term( $existing['term_id'], 'treatment_category', array(
            'name'        => $cat['name'],
            'description' => $cat['description'],
        ));
        $category_ids[ $cat['slug'] ] = $existing['term_id'];
        output( "Updated: {$cat['name']}" );
    } else {
        // Create new
        $result = wp_insert_term( $cat['name'], 'treatment_category', array(
            'slug'        => $cat['slug'],
            'description' => $cat['description'],
        ));

        if ( ! is_wp_error( $result ) ) {
            $category_ids[ $cat['slug'] ] = $result['term_id'];
            output( "Created: {$cat['name']}" );
        } else {
            output( "ERROR creating {$cat['name']}: " . $result->get_error_message() );
        }
    }
}

// =============================================================================
// Step 2: Define treatment assignments
// =============================================================================

// Face area treatments (individual)
$face_treatments = array(
    'Upper Lip',
    'Chin',
    'Sides of Face',
    'Full Face',
    'Neck',
);

// Upper body treatments (individual)
$upper_body_treatments = array(
    'Underarms',
    'Half Arms',
    'Full Arms',
    'Hands & Fingers',
    'Stomach / Abs',
    'Lower Back',
    'Chest',
);

// Lower body treatments (individual)
$lower_body_treatments = array(
    'Bikini Line',
    'Extended Bikini',
    'Brazilian',
    'Hollywood',
    'Perianal',
    'Buttocks',
    'Half Legs',
    'Full Legs',
    'Feet & Toes',
);

// Women packages
$women_packages = array(
    'Essential Full Body (Excl. Face & Neck)',
    'Ultimate Full Body',
    'Smooth Legs & Arms',
    'Brazilian & Underarms',
    'Face & Neck',
);

// Men treatments/packages (anything with "Men" in the name)
$men_treatments = array(
    "Men's Full Face",
    "Men's Neck",
    "Men's Shoulders",
    "Men's Chest",
    "Men's Abs",
    "Men's Full Back",
    "Men's Underarms",
    "Men's Full Arms",
    "Men's Bikini Line",
    "Men's Brazilian",
    "Men's Hollywood",
    "Men's Full Legs",
    "Men's Essential Full Body",
    "Men's Ultimate Full Body",
    "Men's Chest & Back",
);

// =============================================================================
// Step 3: Reassign treatments to categories
// =============================================================================
output( "<h2>Step 2: Reassigning Treatments</h2>" );

// Get all treatments
$all_treatments = get_posts( array(
    'post_type'      => 'treatment',
    'post_status'    => 'any',
    'posts_per_page' => -1,
));

output( "Found " . count( $all_treatments ) . " treatments" );

foreach ( $all_treatments as $treatment ) {
    $title = $treatment->post_title;
    $new_category = null;

    // Check which category this treatment belongs to
    if ( in_array( $title, $men_treatments ) || strpos( $title, "Men's" ) !== false ) {
        $new_category = 'men-packages';
    } elseif ( in_array( $title, $women_packages ) ) {
        $new_category = 'women-packages';
    } elseif ( in_array( $title, $face_treatments ) ) {
        $new_category = 'individual-face-area';
    } elseif ( in_array( $title, $upper_body_treatments ) ) {
        $new_category = 'individual-upper-body';
    } elseif ( in_array( $title, $lower_body_treatments ) ) {
        $new_category = 'individual-lower-body';
    }

    if ( $new_category && isset( $category_ids[ $new_category ] ) ) {
        // Remove all existing categories and set the new one
        wp_set_object_terms( $treatment->ID, array( (int) $category_ids[ $new_category ] ), 'treatment_category' );
        output( "Assigned '{$title}' => {$new_category}" );
    } else {
        output( "<span style='color:orange;'>WARNING: No category match for '{$title}'</span>" );
    }
}

// =============================================================================
// Step 4: Delete old unused categories
// =============================================================================
output( "<h2>Step 3: Cleaning Up Old Categories</h2>" );

$old_slugs_to_delete = array(
    'womens-laser-hair-removal',
    'mens-laser-hair-removal',
    'womens-packages',
    'mens-packages',
);

foreach ( $old_slugs_to_delete as $slug ) {
    $term = get_term_by( 'slug', $slug, 'treatment_category' );
    if ( $term && ! in_array( $slug, array_keys( $category_ids ) ) ) {
        wp_delete_term( $term->term_id, 'treatment_category' );
        output( "Deleted old category: {$slug}" );
    }
}

// =============================================================================
// Step 5: Summary
// =============================================================================
output( "<h2>Summary</h2>" );

$final_categories = get_terms( array(
    'taxonomy'   => 'treatment_category',
    'hide_empty' => false,
));

echo "<table border='1' cellpadding='8'><tr><th>Category</th><th>Slug</th><th>Treatment Count</th></tr>";
foreach ( $final_categories as $cat ) {
    echo "<tr><td>{$cat->name}</td><td>{$cat->slug}</td><td>{$cat->count}</td></tr>";
}
echo "</table>";

output( "<br><strong style='color:green;'>Done! Categories have been fixed.</strong>" );
output( "<br><strong style='color:red;'>DELETE THIS FILE AFTER USE!</strong>" );
