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
            'name'                       => _x( 'Treatment Categories', 'taxonomy general name', 'treatment-packages-deposits' ),
            'singular_name'              => _x( 'Treatment Category', 'taxonomy singular name', 'treatment-packages-deposits' ),
            'search_items'               => __( 'Search Categories', 'treatment-packages-deposits' ),
            'popular_items'              => __( 'Popular Categories', 'treatment-packages-deposits' ),
            'all_items'                  => __( 'All Categories', 'treatment-packages-deposits' ),
            'parent_item'                => __( 'Parent Category', 'treatment-packages-deposits' ),
            'parent_item_colon'          => __( 'Parent Category:', 'treatment-packages-deposits' ),
            'edit_item'                  => __( 'Edit Category', 'treatment-packages-deposits' ),
            'view_item'                  => __( 'View Category', 'treatment-packages-deposits' ),
            'update_item'                => __( 'Update Category', 'treatment-packages-deposits' ),
            'add_new_item'               => __( 'Add New Category', 'treatment-packages-deposits' ),
            'new_item_name'              => __( 'New Category Name', 'treatment-packages-deposits' ),
            'separate_items_with_commas' => __( 'Separate categories with commas', 'treatment-packages-deposits' ),
            'add_or_remove_items'        => __( 'Add or remove categories', 'treatment-packages-deposits' ),
            'choose_from_most_used'      => __( 'Choose from the most used categories', 'treatment-packages-deposits' ),
            'not_found'                  => __( 'No categories found.', 'treatment-packages-deposits' ),
            'no_terms'                   => __( 'No categories', 'treatment-packages-deposits' ),
            'menu_name'                  => __( 'Categories', 'treatment-packages-deposits' ),
            'items_list_navigation'      => __( 'Categories list navigation', 'treatment-packages-deposits' ),
            'items_list'                 => __( 'Categories list', 'treatment-packages-deposits' ),
            'back_to_items'              => __( '&larr; Back to Categories', 'treatment-packages-deposits' ),
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
            'name'                       => _x( 'Treatment Areas', 'taxonomy general name', 'treatment-packages-deposits' ),
            'singular_name'              => _x( 'Treatment Area', 'taxonomy singular name', 'treatment-packages-deposits' ),
            'search_items'               => __( 'Search Areas', 'treatment-packages-deposits' ),
            'popular_items'              => __( 'Popular Areas', 'treatment-packages-deposits' ),
            'all_items'                  => __( 'All Areas', 'treatment-packages-deposits' ),
            'parent_item'                => null,
            'parent_item_colon'          => null,
            'edit_item'                  => __( 'Edit Area', 'treatment-packages-deposits' ),
            'view_item'                  => __( 'View Area', 'treatment-packages-deposits' ),
            'update_item'                => __( 'Update Area', 'treatment-packages-deposits' ),
            'add_new_item'               => __( 'Add New Area', 'treatment-packages-deposits' ),
            'new_item_name'              => __( 'New Area Name', 'treatment-packages-deposits' ),
            'separate_items_with_commas' => __( 'Separate areas with commas', 'treatment-packages-deposits' ),
            'add_or_remove_items'        => __( 'Add or remove areas', 'treatment-packages-deposits' ),
            'choose_from_most_used'      => __( 'Choose from the most used areas', 'treatment-packages-deposits' ),
            'not_found'                  => __( 'No areas found.', 'treatment-packages-deposits' ),
            'no_terms'                   => __( 'No areas', 'treatment-packages-deposits' ),
            'menu_name'                  => __( 'Body Areas', 'treatment-packages-deposits' ),
            'items_list_navigation'      => __( 'Areas list navigation', 'treatment-packages-deposits' ),
            'items_list'                 => __( 'Areas list', 'treatment-packages-deposits' ),
            'back_to_items'              => __( '&larr; Back to Areas', 'treatment-packages-deposits' ),
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
            'tax_query'      => $tax_query,
            'orderby'        => 'menu_order title',
            'order'          => 'ASC',
        );

        $args = wp_parse_args( $args, $defaults );

        return new \WP_Query( $args );
    }
}
