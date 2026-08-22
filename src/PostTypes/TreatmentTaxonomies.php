<?php

namespace TreatmentPackages\PostTypes;

defined('ABSPATH') || exit;

/**
 * Treatment Taxonomies
 *
 * Registers categories and tags for treatments
 */
class TreatmentTaxonomies {

    /**
     * Category taxonomy slug
     */
    const CATEGORY = 'tp_treatment_cat';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'register'], 5);
    }

    /**
     * Register taxonomies
     */
    public function register(): void {
        $this->register_category();
    }

    /**
     * Register treatment category taxonomy
     */
    private function register_category(): void {
        $labels = [
            'name'                       => _x('Treatment Categories', 'Taxonomy general name', 'treatpack'),
            'singular_name'              => _x('Treatment Category', 'Taxonomy singular name', 'treatpack'),
            'search_items'               => __('Search Categories', 'treatpack'),
            'popular_items'              => __('Popular Categories', 'treatpack'),
            'all_items'                  => __('All Categories', 'treatpack'),
            'parent_item'                => __('Parent Category', 'treatpack'),
            'parent_item_colon'          => __('Parent Category:', 'treatpack'),
            'edit_item'                  => __('Edit Category', 'treatpack'),
            'view_item'                  => __('View Category', 'treatpack'),
            'update_item'                => __('Update Category', 'treatpack'),
            'add_new_item'               => __('Add New Category', 'treatpack'),
            'new_item_name'              => __('New Category Name', 'treatpack'),
            'separate_items_with_commas' => __('Separate categories with commas', 'treatpack'),
            'add_or_remove_items'        => __('Add or remove categories', 'treatpack'),
            'choose_from_most_used'      => __('Choose from the most used categories', 'treatpack'),
            'not_found'                  => __('No categories found.', 'treatpack'),
            'no_terms'                   => __('No categories', 'treatpack'),
            'menu_name'                  => __('Categories', 'treatpack'),
            'items_list_navigation'      => __('Categories list navigation', 'treatpack'),
            'items_list'                 => __('Categories list', 'treatpack'),
            'back_to_items'              => __('&larr; Back to Categories', 'treatpack'),
        ];

        $args = [
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => true,
            'show_ui'           => true,
            'show_admin_column' => true,
            'show_in_nav_menus' => true,
            'show_tagcloud'     => false,
            'show_in_rest'      => true,
            'rewrite'           => [
                'slug'         => 'treatment-category',
                'with_front'   => false,
                'hierarchical' => true,
            ],
        ];

        register_taxonomy(self::CATEGORY, TreatmentPostType::POST_TYPE, $args);
    }

    /**
     * Get all treatment categories
     *
     * @param array $args Optional. Arguments to pass to get_terms()
     * @return array Array of term objects
     */
    public static function get_categories(array $args = []): array {
        $defaults = [
            'taxonomy'   => self::CATEGORY,
            'hide_empty' => false,
            'orderby'    => 'name',
            'order'      => 'ASC',
        ];

        $args = wp_parse_args($args, $defaults);

        return get_terms($args);
    }

    /**
     * Get treatment categories as options for select dropdown
     *
     * @param bool $include_empty Whether to include empty categories
     * @return array Associative array of term_id => name
     */
    public static function get_categories_options(bool $include_empty = true): array {
        $categories = self::get_categories(['hide_empty' => !$include_empty]);
        $options = [];

        foreach ($categories as $category) {
            $options[$category->term_id] = $category->name;
        }

        return $options;
    }
}
