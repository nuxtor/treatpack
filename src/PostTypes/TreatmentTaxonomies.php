<?php
/**
 * Treatment Taxonomies
 *
 * @package TreatmentPackages\PostTypes
 */

namespace TreatmentPackages\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * TreatmentTaxonomies Class
 *
 * Registers custom taxonomies for the Treatment post type.
 */
class TreatmentTaxonomies {

    /**
     * Category taxonomy slug
     *
     * @var string
     */
    const CATEGORY_TAXONOMY = 'treatment_category';

    /**
     * Area taxonomy slug
     *
     * @var string
     */
    const AREA_TAXONOMY = 'treatment_area';

    /**
     * Register taxonomies
     */
    public static function register() {
        add_action( 'init', array( __CLASS__, 'register_taxonomies' ) );
    }

    /**
     * Register all taxonomies
     */
    public static function register_taxonomies() {
        self::register_category_taxonomy();
        self::register_area_taxonomy();
    }

    /**
     * Register treatment_category taxonomy
     */
    private static function register_category_taxonomy() {
        $labels = array(
            'name'                       => _x( 'Treatment Categories', 'taxonomy general name', 'treatpack' ),
            'singular_name'              => _x( 'Treatment Category', 'taxonomy singular name', 'treatpack' ),
            'search_items'               => __( 'Search Categories', 'treatpack' ),
            'popular_items'              => __( 'Popular Categories', 'treatpack' ),
            'all_items'                  => __( 'All Categories', 'treatpack' ),
            'parent_item'                => __( 'Parent Category', 'treatpack' ),
            'parent_item_colon'          => __( 'Parent Category:', 'treatpack' ),
            'edit_item'                  => __( 'Edit Category', 'treatpack' ),
            'view_item'                  => __( 'View Category', 'treatpack' ),
            'update_item'                => __( 'Update Category', 'treatpack' ),
            'add_new_item'               => __( 'Add New Category', 'treatpack' ),
            'new_item_name'              => __( 'New Category Name', 'treatpack' ),
            'separate_items_with_commas' => __( 'Separate categories with commas', 'treatpack' ),
            'add_or_remove_items'        => __( 'Add or remove categories', 'treatpack' ),
            'choose_from_most_used'      => __( 'Choose from the most used categories', 'treatpack' ),
            'not_found'                  => __( 'No categories found.', 'treatpack' ),
            'no_terms'                   => __( 'No categories', 'treatpack' ),
            'menu_name'                  => __( 'Categories', 'treatpack' ),
            'items_list_navigation'      => __( 'Categories list navigation', 'treatpack' ),
            'items_list'                 => __( 'Categories list', 'treatpack' ),
            'back_to_items'              => __( '&larr; Back to Categories', 'treatpack' ),
        );

        $args = array(
            'labels'             => $labels,
            'hierarchical'       => true,
            'public'             => true,
            'show_ui'            => true,
            'show_admin_column'  => true,
            'show_in_nav_menus'  => true,
            'show_tagcloud'      => false,
            'show_in_rest'       => true,
            'query_var'          => true,
            'rewrite'            => array(
                'slug'         => 'treatment-category',
                'with_front'   => false,
                'hierarchical' => true,
            ),
        );

        register_taxonomy(
            self::CATEGORY_TAXONOMY,
            TreatmentPostType::POST_TYPE,
            $args
        );
    }

    /**
     * Register treatment_area taxonomy
     */
    private static function register_area_taxonomy() {
        $labels = array(
            'name'                       => _x( 'Treatment Areas', 'taxonomy general name', 'treatpack' ),
            'singular_name'              => _x( 'Treatment Area', 'taxonomy singular name', 'treatpack' ),
            'search_items'               => __( 'Search Areas', 'treatpack' ),
            'popular_items'              => __( 'Popular Areas', 'treatpack' ),
            'all_items'                  => __( 'All Areas', 'treatpack' ),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __( 'Edit Area', 'treatpack' ),
            'view_item'                  => __( 'View Area', 'treatpack' ),
            'update_item'                => __( 'Update Area', 'treatpack' ),
            'add_new_item'               => __( 'Add New Area', 'treatpack' ),
            'new_item_name'              => __( 'New Area Name', 'treatpack' ),
            'separate_items_with_commas' => __( 'Separate areas with commas', 'treatpack' ),
            'add_or_remove_items'        => __( 'Add or remove areas', 'treatpack' ),
            'choose_from_most_used'      => __( 'Choose from the most used areas', 'treatpack' ),
            'not_found'                  => __( 'No areas found.', 'treatpack' ),
            'no_terms'                   => __( 'No areas', 'treatpack' ),
            'menu_name'                  => __( 'Body Areas', 'treatpack' ),
            'items_list_navigation'      => __( 'Areas list navigation', 'treatpack' ),
            'items_list'                 => __( 'Areas list', 'treatpack' ),
            'back_to_items'              => __( '&larr; Back to Areas', 'treatpack' ),
        );

        $args = array(
            'labels'             => $labels,
            'hierarchical'       => false, // Like tags
            'public'             => true,
            'show_ui'            => true,
            'show_admin_column'  => true,
            'show_in_nav_menus'  => true,
            'show_tagcloud'      => true,
            'show_in_rest'       => true,
            'query_var'          => true,
            'rewrite'            => array(
                'slug'       => 'treatment-area',
                'with_front' => false,
            ),
        );

        register_taxonomy(
            self::AREA_TAXONOMY,
            TreatmentPostType::POST_TYPE,
            $args
        );
    }

    /**
     * Get all treatment categories
     *
     * @param array $args Optional. Arguments to pass to get_terms().
     * @return array Array of term objects.
     */
    public static function get_categories( $args = array() ) {
        $defaults = array(
            'taxonomy'   => self::CATEGORY_TAXONOMY,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        );

        $args = wp_parse_args( $args, $defaults );

        return get_terms( $args );
    }

    /**
     * Get all treatment areas
     *
     * @param array $args Optional. Arguments to pass to get_terms().
     * @return array Array of term objects.
     */
    public static function get_areas( $args = array() ) {
        $defaults = array(
            'taxonomy'   => self::AREA_TAXONOMY,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        );

        $args = wp_parse_args( $args, $defaults );

        return get_terms( $args );
    }

    /**
     * Get treatments by category
     *
     * @param string|int $category Category slug or ID.
     * @param array      $args     Optional. Additional query arguments.
     * @return \WP_Query Query object with treatments.
     */
    public static function get_treatments_by_category( $category, $args = array() ) {
        $tax_query = array(
            array(
                'taxonomy' => self::CATEGORY_TAXONOMY,
                'field'    => is_numeric( $category ) ? 'term_id' : 'slug',
                'terms'    => $category,
            ),
        );

        $defaults = array(
            'post_type'      => TreatmentPostType::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'tax_query'      => $tax_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- Taxonomy query required for filtering treatments by category.
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        );

        $args = wp_parse_args( $args, $defaults );

        return new \WP_Query( $args );
    }
}
