<?php

namespace TreatmentPackages\PostTypes;

defined('ABSPATH') || exit;

/**
 * Treatment Custom Post Type
 */
class TreatmentPostType {

    /**
     * Post type slug
     */
    const POST_TYPE = 'tp_treatment';

    /**
     * Constructor
     */
    public function __construct() {
        add_action('init', [$this, 'register'], 5);
        add_action('add_meta_boxes', [$this, 'add_meta_boxes']);
        add_action('save_post_' . self::POST_TYPE, [$this, 'save_meta'], 10, 2);
        add_filter('manage_' . self::POST_TYPE . '_posts_columns', [$this, 'admin_columns']);
        add_action('manage_' . self::POST_TYPE . '_posts_custom_column', [$this, 'admin_column_content'], 10, 2);
    }

    /**
     * Register the post type
     */
    public function register(): void {
        $labels = [
            'name'                  => _x('Treatments', 'Post type general name', 'treatpack'),
            'singular_name'         => _x('Treatment', 'Post type singular name', 'treatpack'),
            'menu_name'             => _x('Treatments', 'Admin Menu text', 'treatpack'),
            'name_admin_bar'        => _x('Treatment', 'Add New on Toolbar', 'treatpack'),
            'add_new'               => __('Add New', 'treatpack'),
            'add_new_item'          => __('Add New Treatment', 'treatpack'),
            'new_item'              => __('New Treatment', 'treatpack'),
            'edit_item'             => __('Edit Treatment', 'treatpack'),
            'view_item'             => __('View Treatment', 'treatpack'),
            'all_items'             => __('All Treatments', 'treatpack'),
            'search_items'          => __('Search Treatments', 'treatpack'),
            'parent_item_colon'     => __('Parent Treatments:', 'treatpack'),
            'not_found'             => __('No treatments found.', 'treatpack'),
            'not_found_in_trash'    => __('No treatments found in Trash.', 'treatpack'),
            'featured_image'        => _x('Treatment Image', 'Overrides the "Featured Image" phrase', 'treatpack'),
            'set_featured_image'    => _x('Set treatment image', 'Overrides the "Set featured image" phrase', 'treatpack'),
            'remove_featured_image' => _x('Remove treatment image', 'Overrides the "Remove featured image" phrase', 'treatpack'),
            'use_featured_image'    => _x('Use as treatment image', 'Overrides the "Use as featured image" phrase', 'treatpack'),
            'archives'              => _x('Treatment archives', 'The post type archive label', 'treatpack'),
            'insert_into_item'      => _x('Insert into treatment', 'Overrides the "Insert into post" phrase', 'treatpack'),
            'uploaded_to_this_item' => _x('Uploaded to this treatment', 'Overrides the "Uploaded to this post" phrase', 'treatpack'),
            'filter_items_list'     => _x('Filter treatments list', 'Screen reader text', 'treatpack'),
            'items_list_navigation' => _x('Treatments list navigation', 'Screen reader text', 'treatpack'),
            'items_list'            => _x('Treatments list', 'Screen reader text', 'treatpack'),
        ];

        $args = [
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => ['slug' => 'treatment', 'with_front' => false],
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 26,
            'menu_icon'          => 'dashicons-heart',
            'supports'           => ['title', 'editor', 'thumbnail', 'excerpt'],
            'show_in_rest'       => true,
        ];

        register_post_type(self::POST_TYPE, $args);
    }

    /**
     * Add meta boxes
     */
    public function add_meta_boxes(): void {
        add_meta_box(
            'treatpack_treatment_settings',
            __('Treatment Settings', 'treatpack'),
            [$this, 'render_settings_meta_box'],
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Render settings meta box
     *
     * @param \WP_Post $post Current post object
     */
    public function render_settings_meta_box(\WP_Post $post): void {
        wp_nonce_field('treatpack_treatment_settings', 'treatpack_treatment_nonce');

        $base_price = get_post_meta($post->ID, '_treatpack_base_price', true);
        $duration = get_post_meta($post->ID, '_treatpack_duration', true);
        $default_deposit_type = get_post_meta($post->ID, '_treatpack_default_deposit_type', true) ?: 'none';
        $default_deposit_amount = get_post_meta($post->ID, '_treatpack_default_deposit_amount', true);
        ?>
        <p>
            <label for="treatpack_base_price">
                <strong><?php esc_html_e('Base Price', 'treatpack'); ?></strong>
            </label>
            <input type="number"
                   id="treatpack_base_price"
                   name="treatpack_base_price"
                   value="<?php echo esc_attr($base_price); ?>"
                   step="0.01"
                   min="0"
                   class="widefat"
                   placeholder="0.00">
            <span class="description"><?php esc_html_e('Single session price', 'treatpack'); ?></span>
        </p>

        <p>
            <label for="treatpack_duration">
                <strong><?php esc_html_e('Duration (minutes)', 'treatpack'); ?></strong>
            </label>
            <input type="number"
                   id="treatpack_duration"
                   name="treatpack_duration"
                   value="<?php echo esc_attr($duration); ?>"
                   min="0"
                   class="widefat"
                   placeholder="60">
        </p>

        <p>
            <label for="treatpack_default_deposit_type">
                <strong><?php esc_html_e('Default Deposit Type', 'treatpack'); ?></strong>
            </label>
            <select id="treatpack_default_deposit_type" name="treatpack_default_deposit_type" class="widefat">
                <option value="none" <?php selected($default_deposit_type, 'none'); ?>>
                    <?php esc_html_e('No deposit (full payment)', 'treatpack'); ?>
                </option>
                <option value="fixed" <?php selected($default_deposit_type, 'fixed'); ?>>
                    <?php esc_html_e('Fixed amount', 'treatpack'); ?>
                </option>
                <option value="percentage" <?php selected($default_deposit_type, 'percentage'); ?>>
                    <?php esc_html_e('Percentage', 'treatpack'); ?>
                </option>
                <option value="pay_at_location" <?php selected($default_deposit_type, 'pay_at_location'); ?>>
                    <?php esc_html_e('Pay at location', 'treatpack'); ?>
                </option>
            </select>
        </p>

        <p id="treatpack_deposit_amount_wrap" style="<?php echo $default_deposit_type === 'none' || $default_deposit_type === 'pay_at_location' ? 'display:none;' : ''; ?>">
            <label for="treatpack_default_deposit_amount">
                <strong><?php esc_html_e('Default Deposit Amount', 'treatpack'); ?></strong>
            </label>
            <input type="number"
                   id="treatpack_default_deposit_amount"
                   name="treatpack_default_deposit_amount"
                   value="<?php echo esc_attr($default_deposit_amount); ?>"
                   step="0.01"
                   min="0"
                   class="widefat">
            <span class="description" id="treatpack_deposit_hint">
                <?php echo $default_deposit_type === 'percentage'
                    ? esc_html__('Enter percentage (e.g., 20 for 20%)', 'treatpack')
                    : esc_html__('Enter fixed amount', 'treatpack'); ?>
            </span>
        </p>

        <script>
        jQuery(function($) {
            $('#treatpack_default_deposit_type').on('change', function() {
                var type = $(this).val();
                var $wrap = $('#treatpack_deposit_amount_wrap');
                var $hint = $('#treatpack_deposit_hint');

                if (type === 'none' || type === 'pay_at_location') {
                    $wrap.hide();
                } else {
                    $wrap.show();
                    $hint.text(type === 'percentage'
                        ? '<?php echo esc_js(__('Enter percentage (e.g., 20 for 20%)', 'treatpack')); ?>'
                        : '<?php echo esc_js(__('Enter fixed amount', 'treatpack')); ?>');
                }
            });
        });
        </script>
        <?php
    }

    /**
     * Save meta box data
     *
     * @param int      $post_id Post ID
     * @param \WP_Post $post    Post object
     */
    public function save_meta(int $post_id, \WP_Post $post): void {
        if (!isset($_POST['treatpack_treatment_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['treatpack_treatment_nonce'], 'treatpack_treatment_settings')) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (!current_user_can('edit_post', $post_id)) {
            return;
        }

        $fields = [
            'treatpack_base_price' => '_treatpack_base_price',
            'treatpack_duration' => '_treatpack_duration',
            'treatpack_default_deposit_type' => '_treatpack_default_deposit_type',
            'treatpack_default_deposit_amount' => '_treatpack_default_deposit_amount',
        ];

        foreach ($fields as $input => $meta_key) {
            if (isset($_POST[$input])) {
                $value = sanitize_text_field($_POST[$input]);
                update_post_meta($post_id, $meta_key, $value);
            }
        }
    }

    /**
     * Customize admin columns
     *
     * @param array $columns Existing columns
     * @return array Modified columns
     */
    public function admin_columns(array $columns): array {
        $new_columns = [];

        foreach ($columns as $key => $value) {
            $new_columns[$key] = $value;

            if ($key === 'title') {
                $new_columns['base_price'] = __('Base Price', 'treatpack');
                $new_columns['duration'] = __('Duration', 'treatpack');
                $new_columns['packages'] = __('Packages', 'treatpack');
            }
        }

        return $new_columns;
    }

    /**
     * Render admin column content
     *
     * @param string $column  Column name
     * @param int    $post_id Post ID
     */
    public function admin_column_content(string $column, int $post_id): void {
        switch ($column) {
            case 'base_price':
                $price = get_post_meta($post_id, '_treatpack_base_price', true);
                if ($price) {
                    echo wc_price($price);
                } else {
                    echo '—';
                }
                break;

            case 'duration':
                $duration = get_post_meta($post_id, '_treatpack_duration', true);
                if ($duration) {
                    printf(
                        _n('%d minute', '%d minutes', $duration, 'treatpack'),
                        $duration
                    );
                } else {
                    echo '—';
                }
                break;

            case 'packages':
                global $wpdb;
                $table = \TreatmentPackages\DB\Installer::get_packages_table();
                $count = $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM $table WHERE treatment_id = %d AND is_active = 1",
                    $post_id
                ));
                echo intval($count);
                break;
        }
    }
}
