<?php
/**
 * Treatment Custom Post Type
 *
 * @package TreatmentPackages\PostTypes
 */

namespace TreatmentPackages\PostTypes;

defined( 'ABSPATH' ) || exit;

/**
 * TreatmentPostType Class
 *
 * Registers and manages the Treatment custom post type.
 */
class TreatmentPostType {

    /**
     * Post type slug
     *
     * @var string
     */
    const POST_TYPE = 'treatment';

    /**
     * Register the post type
     */
    public static function register() {
        add_action( 'init', array( __CLASS__, 'register_post_type' ) );
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
        add_action( 'save_post_' . self::POST_TYPE, array( __CLASS__, 'save_meta' ), 10, 2 );

        // Admin columns
        add_filter( 'manage_' . self::POST_TYPE . '_posts_columns', array( __CLASS__, 'add_admin_columns' ) );
        add_action( 'manage_' . self::POST_TYPE . '_posts_custom_column', array( __CLASS__, 'render_admin_columns' ), 10, 2 );
    }

    /**
     * Register the Treatment post type
     */
    public static function register_post_type() {
        $labels = array(
            'name'                  => _x( 'Treatments', 'Post type general name', 'treatment-packages-deposits' ),
            'singular_name'         => _x( 'Treatment', 'Post type singular name', 'treatment-packages-deposits' ),
            'menu_name'             => _x( 'Treatments', 'Admin Menu text', 'treatment-packages-deposits' ),
            'name_admin_bar'        => _x( 'Treatment', 'Add New on Toolbar', 'treatment-packages-deposits' ),
            'add_new'               => __( 'Add New', 'treatment-packages-deposits' ),
            'add_new_item'          => __( 'Add New Treatment', 'treatment-packages-deposits' ),
            'new_item'              => __( 'New Treatment', 'treatment-packages-deposits' ),
            'edit_item'             => __( 'Edit Treatment', 'treatment-packages-deposits' ),
            'view_item'             => __( 'View Treatment', 'treatment-packages-deposits' ),
            'all_items'             => __( 'All Treatments', 'treatment-packages-deposits' ),
            'search_items'          => __( 'Search Treatments', 'treatment-packages-deposits' ),
            'parent_item_colon'     => __( 'Parent Treatments:', 'treatment-packages-deposits' ),
            'not_found'             => __( 'No treatments found.', 'treatment-packages-deposits' ),
            'not_found_in_trash'    => __( 'No treatments found in Trash.', 'treatment-packages-deposits' ),
            'featured_image'        => _x( 'Treatment Image', 'Overrides the "Featured Image" phrase', 'treatment-packages-deposits' ),
            'set_featured_image'    => _x( 'Set treatment image', 'Overrides the "Set featured image" phrase', 'treatment-packages-deposits' ),
            'remove_featured_image' => _x( 'Remove treatment image', 'Overrides the "Remove featured image" phrase', 'treatment-packages-deposits' ),
            'use_featured_image'    => _x( 'Use as treatment image', 'Overrides the "Use as featured image" phrase', 'treatment-packages-deposits' ),
            'archives'              => _x( 'Treatment archives', 'The post type archive label', 'treatment-packages-deposits' ),
            'insert_into_item'      => _x( 'Insert into treatment', 'Overrides the "Insert into post" phrase', 'treatment-packages-deposits' ),
            'uploaded_to_this_item' => _x( 'Uploaded to this treatment', 'Overrides the "Uploaded to this post" phrase', 'treatment-packages-deposits' ),
            'filter_items_list'     => _x( 'Filter treatments list', 'Screen reader text', 'treatment-packages-deposits' ),
            'items_list_navigation' => _x( 'Treatments list navigation', 'Screen reader text', 'treatment-packages-deposits' ),
            'items_list'            => _x( 'Treatments list', 'Screen reader text', 'treatment-packages-deposits' ),
        );

        $args = array(
            'labels'             => $labels,
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'query_var'          => true,
            'rewrite'            => array( 'slug' => 'treatment', 'with_front' => false ),
            'capability_type'    => 'post',
            'has_archive'        => true,
            'hierarchical'       => false,
            'menu_position'      => 25,
            'menu_icon'          => 'dashicons-heart',
            'supports'           => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
            'show_in_rest'       => true,
        );

        register_post_type( self::POST_TYPE, $args );
    }

    /**
     * Add meta boxes
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'tp_treatment_settings',
            __( 'Treatment Settings', 'treatment-packages-deposits' ),
            array( __CLASS__, 'render_settings_meta_box' ),
            self::POST_TYPE,
            'side',
            'default'
        );
    }

    /**
     * Render the settings meta box
     *
     * @param \WP_Post $post Current post object.
     */
    public static function render_settings_meta_box( $post ) {
        // Get saved values
        $default_deposit_type  = get_post_meta( $post->ID, '_tp_default_deposit_type', true );
        $default_deposit_value = get_post_meta( $post->ID, '_tp_default_deposit_value', true );
        $base_price            = get_post_meta( $post->ID, '_tp_base_price', true );

        // Nonce for security
        wp_nonce_field( 'tp_treatment_settings', 'tp_treatment_settings_nonce' );
        ?>
        <div class="tp-meta-box">
            <p>
                <label for="tp_base_price">
                    <strong><?php esc_html_e( 'Base Price (Single Session)', 'treatment-packages-deposits' ); ?></strong>
                </label>
                <br>
                <input
                    type="number"
                    id="tp_base_price"
                    name="tp_base_price"
                    value="<?php echo esc_attr( $base_price ); ?>"
                    step="0.01"
                    min="0"
                    style="width: 100%;"
                    placeholder="0.00"
                >
                <span class="description">
                    <?php esc_html_e( 'Used to calculate discount percentages.', 'treatment-packages-deposits' ); ?>
                </span>
            </p>

            <hr>

            <p>
                <label for="tp_default_deposit_type">
                    <strong><?php esc_html_e( 'Default Deposit Type', 'treatment-packages-deposits' ); ?></strong>
                </label>
                <br>
                <select id="tp_default_deposit_type" name="tp_default_deposit_type" style="width: 100%;">
                    <option value="none" <?php selected( $default_deposit_type, 'none' ); ?>>
                        <?php esc_html_e( 'No Deposit (Full Payment)', 'treatment-packages-deposits' ); ?>
                    </option>
                    <option value="fixed" <?php selected( $default_deposit_type, 'fixed' ); ?>>
                        <?php esc_html_e( 'Fixed Amount', 'treatment-packages-deposits' ); ?>
                    </option>
                    <option value="percentage" <?php selected( $default_deposit_type, 'percentage' ); ?>>
                        <?php esc_html_e( 'Percentage', 'treatment-packages-deposits' ); ?>
                    </option>
                </select>
            </p>

            <p>
                <label for="tp_default_deposit_value">
                    <strong><?php esc_html_e( 'Default Deposit Value', 'treatment-packages-deposits' ); ?></strong>
                </label>
                <br>
                <input
                    type="number"
                    id="tp_default_deposit_value"
                    name="tp_default_deposit_value"
                    value="<?php echo esc_attr( $default_deposit_value ); ?>"
                    step="0.01"
                    min="0"
                    style="width: 100%;"
                    placeholder="0.00"
                >
                <span class="description">
                    <?php esc_html_e( 'Default deposit for new packages.', 'treatment-packages-deposits' ); ?>
                </span>
            </p>
        </div>
        <?php
    }

    /**
     * Save meta box data
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     */
    public static function save_meta( $post_id, $post ) {
        // Verify nonce
        if ( ! isset( $_POST['tp_treatment_settings_nonce'] ) ||
             ! wp_verify_nonce( $_POST['tp_treatment_settings_nonce'], 'tp_treatment_settings' ) ) {
            return;
        }

        // Check autosave
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        // Check permissions
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        // Save base price
        if ( isset( $_POST['tp_base_price'] ) ) {
            $base_price = sanitize_text_field( $_POST['tp_base_price'] );
            update_post_meta( $post_id, '_tp_base_price', $base_price );
        }

        // Save default deposit type
        if ( isset( $_POST['tp_default_deposit_type'] ) ) {
            $deposit_type = sanitize_text_field( $_POST['tp_default_deposit_type'] );
            if ( in_array( $deposit_type, array( 'none', 'fixed', 'percentage' ), true ) ) {
                update_post_meta( $post_id, '_tp_default_deposit_type', $deposit_type );
            }
        }

        // Save default deposit value
        if ( isset( $_POST['tp_default_deposit_value'] ) ) {
            $deposit_value = sanitize_text_field( $_POST['tp_default_deposit_value'] );
            update_post_meta( $post_id, '_tp_default_deposit_value', $deposit_value );
        }
    }

    /**
     * Add custom admin columns
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public static function add_admin_columns( $columns ) {
        $new_columns = array();

        foreach ( $columns as $key => $value ) {
            $new_columns[ $key ] = $value;

            // Add columns after title
            if ( 'title' === $key ) {
                $new_columns['tp_category']  = __( 'Category', 'treatment-packages-deposits' );
                $new_columns['tp_packages']  = __( 'Packages', 'treatment-packages-deposits' );
                $new_columns['tp_base_price'] = __( 'Base Price', 'treatment-packages-deposits' );
            }
        }

        return $new_columns;
    }

    /**
     * Render custom admin columns
     *
     * @param string $column  Column name.
     * @param int    $post_id Post ID.
     */
    public static function render_admin_columns( $column, $post_id ) {
        switch ( $column ) {
            case 'tp_category':
                $terms = get_the_terms( $post_id, 'treatment_category' );
                if ( $terms && ! is_wp_error( $terms ) ) {
                    $term_names = wp_list_pluck( $terms, 'name' );
                    echo esc_html( implode( ', ', $term_names ) );
                } else {
                    echo '—';
                }
                break;

            case 'tp_packages':
                global $wpdb;
                $table_name = $wpdb->prefix . 'tp_packages';
                $count = $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$table_name} WHERE treatment_id = %d",
                        $post_id
                    )
                );
                echo esc_html( $count ?: '0' );
                break;

            case 'tp_base_price':
                $base_price = get_post_meta( $post_id, '_tp_base_price', true );
                if ( $base_price ) {
                    echo esc_html( get_woocommerce_currency_symbol() . number_format( (float) $base_price, 2 ) );
                } else {
                    echo '—';
                }
                break;
        }
    }
}
