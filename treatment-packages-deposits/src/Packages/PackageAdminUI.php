<?php
/**
 * Package Admin UI
 *
 * @package TreatmentPackages\Packages
 */

namespace TreatmentPackages\Packages;

use TreatmentPackages\PostTypes\TreatmentPostType;

defined( 'ABSPATH' ) || exit;

/**
 * PackageAdminUI Class
 *
 * Handles the admin interface for managing treatment packages.
 */
class PackageAdminUI {

    /**
     * Initialize the admin UI
     */
    public static function init() {
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_meta_boxes' ) );
        add_action( 'save_post_' . TreatmentPostType::POST_TYPE, array( __CLASS__, 'save_packages' ), 20, 2 );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );

        // AJAX handlers
        add_action( 'wp_ajax_tp_get_package_row', array( __CLASS__, 'ajax_get_package_row' ) );
    }

    /**
     * Add meta boxes
     */
    public static function add_meta_boxes() {
        add_meta_box(
            'tp_packages_meta_box',
            __( 'Treatment Packages', 'treatment-packages-deposits' ),
            array( __CLASS__, 'render_packages_meta_box' ),
            TreatmentPostType::POST_TYPE,
            'normal',
            'high'
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_admin_assets( $hook ) {
        global $post_type;

        // Only load on treatment edit screens
        if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
            return;
        }

        if ( TreatmentPostType::POST_TYPE !== $post_type ) {
            return;
        }

        wp_enqueue_style(
            'tp-admin-packages',
            TP_DEPOSITS_PLUGIN_URL . 'assets/css/admin-packages.css',
            array(),
            TP_DEPOSITS_VERSION
        );

        wp_enqueue_script(
            'tp-admin-packages',
            TP_DEPOSITS_PLUGIN_URL . 'assets/js/admin-packages.js',
            array( 'jquery', 'jquery-ui-sortable' ),
            TP_DEPOSITS_VERSION,
            true
        );

        wp_localize_script(
            'tp-admin-packages',
            'tpAdminPackages',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'tp_admin_packages' ),
                'i18n'    => array(
                    'confirmDelete'  => __( 'Are you sure you want to delete this package?', 'treatment-packages-deposits' ),
                    'payAsYouGo'     => __( 'Pay as you go', 'treatment-packages-deposits' ),
                    'courseOf'       => __( 'Course of %d', 'treatment-packages-deposits' ),
                ),
            )
        );
    }

    /**
     * Render the packages meta box
     *
     * @param \WP_Post $post Current post object.
     */
    public static function render_packages_meta_box( $post ) {
        // Get existing packages
        $packages = PackageRepository::get_by_treatment( $post->ID );

        // Get default deposit settings from treatment
        $default_deposit_type  = get_post_meta( $post->ID, '_tp_default_deposit_type', true ) ?: 'none';
        $default_deposit_value = get_post_meta( $post->ID, '_tp_default_deposit_value', true ) ?: '';

        wp_nonce_field( 'tp_save_packages', 'tp_packages_nonce' );
        ?>
        <div class="tp-packages-admin">
            <p class="description">
                <?php esc_html_e( 'Add session packages for this treatment. Each package can have its own pricing and deposit settings.', 'treatment-packages-deposits' ); ?>
            </p>

            <table class="tp-packages-table widefat">
                <thead>
                    <tr>
                        <th class="tp-col-sort" title="<?php esc_attr_e( 'Drag to reorder', 'treatment-packages-deposits' ); ?>"></th>
                        <th class="tp-col-sessions"><?php esc_html_e( 'Sessions', 'treatment-packages-deposits' ); ?></th>
                        <th class="tp-col-name"><?php esc_html_e( 'Name', 'treatment-packages-deposits' ); ?></th>
                        <th class="tp-col-price"><?php esc_html_e( 'Total Price', 'treatment-packages-deposits' ); ?></th>
                        <th class="tp-col-per-session"><?php esc_html_e( 'Per Session', 'treatment-packages-deposits' ); ?></th>
                        <th class="tp-col-discount"><?php esc_html_e( 'Discount', 'treatment-packages-deposits' ); ?></th>
                        <th class="tp-col-deposit"><?php esc_html_e( 'Deposit', 'treatment-packages-deposits' ); ?></th>
                        <th class="tp-col-actions"><?php esc_html_e( 'Actions', 'treatment-packages-deposits' ); ?></th>
                    </tr>
                </thead>
                <tbody id="tp-packages-list">
                    <?php
                    if ( ! empty( $packages ) ) {
                        foreach ( $packages as $index => $package ) {
                            self::render_package_row( $package, $index );
                        }
                    }
                    ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="8">
                            <button type="button" class="button button-secondary" id="tp-add-package">
                                <span class="dashicons dashicons-plus-alt2"></span>
                                <?php esc_html_e( 'Add Package', 'treatment-packages-deposits' ); ?>
                            </button>

                            <button type="button" class="button button-secondary" id="tp-add-preset-packages">
                                <span class="dashicons dashicons-database-add"></span>
                                <?php esc_html_e( 'Add Preset (1, 6, 8, 10)', 'treatment-packages-deposits' ); ?>
                            </button>
                        </td>
                    </tr>
                </tfoot>
            </table>

            <!-- Hidden template for new rows -->
            <script type="text/html" id="tp-package-row-template">
                <?php self::render_package_row( null, '{{INDEX}}' ); ?>
            </script>

            <!-- Store default deposit settings for JS -->
            <input type="hidden" id="tp-default-deposit-type" value="<?php echo esc_attr( $default_deposit_type ); ?>">
            <input type="hidden" id="tp-default-deposit-value" value="<?php echo esc_attr( $default_deposit_value ); ?>">
        </div>
        <?php
    }

    /**
     * Render a single package row
     *
     * @param PackageModel|null $package Package model or null for template.
     * @param int|string        $index   Row index.
     */
    public static function render_package_row( $package, $index ) {
        $id             = $package ? $package->get_id() : '';
        $sessions       = $package ? $package->get_sessions() : 1;
        $name           = $package ? $package->get_name() : '';
        $total_price    = $package ? $package->get_total_price() : '';
        $per_session    = $package ? $package->get_per_session_price() : '';
        $discount       = $package ? $package->get_discount_percent() : '';
        $deposit_type   = $package ? $package->get_deposit_type() : 'none';
        $deposit_value  = $package ? $package->get_deposit_value() : '';
        $wc_product_id  = $package ? $package->get_wc_product_id() : '';

        $currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '£';
        ?>
        <tr class="tp-package-row" data-index="<?php echo esc_attr( $index ); ?>">
            <td class="tp-col-sort">
                <span class="tp-sort-handle dashicons dashicons-menu" title="<?php esc_attr_e( 'Drag to reorder', 'treatment-packages-deposits' ); ?>"></span>
                <input type="hidden" name="tp_packages[<?php echo esc_attr( $index ); ?>][id]" value="<?php echo esc_attr( $id ); ?>">
                <input type="hidden" name="tp_packages[<?php echo esc_attr( $index ); ?>][sort_order]" value="<?php echo esc_attr( $index ); ?>" class="tp-sort-order">
            </td>

            <td class="tp-col-sessions">
                <input
                    type="number"
                    name="tp_packages[<?php echo esc_attr( $index ); ?>][sessions]"
                    value="<?php echo esc_attr( $sessions ); ?>"
                    min="1"
                    max="100"
                    class="tp-input-sessions small-text"
                    required
                >
            </td>

            <td class="tp-col-name">
                <input
                    type="text"
                    name="tp_packages[<?php echo esc_attr( $index ); ?>][name]"
                    value="<?php echo esc_attr( $name ); ?>"
                    class="tp-input-name"
                    placeholder="<?php esc_attr_e( 'Auto-generated if empty', 'treatment-packages-deposits' ); ?>"
                >
            </td>

            <td class="tp-col-price">
                <div class="tp-input-group">
                    <span class="tp-currency"><?php echo esc_html( $currency_symbol ); ?></span>
                    <input
                        type="number"
                        name="tp_packages[<?php echo esc_attr( $index ); ?>][total_price]"
                        value="<?php echo esc_attr( $total_price ); ?>"
                        min="0"
                        step="0.01"
                        class="tp-input-price"
                        required
                    >
                </div>
            </td>

            <td class="tp-col-per-session">
                <span class="tp-per-session-display">
                    <?php
                    if ( $per_session ) {
                        echo esc_html( $currency_symbol . number_format( $per_session, 2 ) );
                    } else {
                        echo '—';
                    }
                    ?>
                </span>
            </td>

            <td class="tp-col-discount">
                <span class="tp-discount-display">
                    <?php
                    if ( $discount > 0 ) {
                        echo esc_html( number_format( $discount, 0 ) . '%' );
                    } else {
                        echo '—';
                    }
                    ?>
                </span>
            </td>

            <td class="tp-col-deposit">
                <div class="tp-deposit-fields">
                    <select name="tp_packages[<?php echo esc_attr( $index ); ?>][deposit_type]" class="tp-input-deposit-type">
                        <option value="none" <?php selected( $deposit_type, 'none' ); ?>><?php esc_html_e( 'Full Payment', 'treatment-packages-deposits' ); ?></option>
                        <option value="fixed" <?php selected( $deposit_type, 'fixed' ); ?>><?php esc_html_e( 'Fixed', 'treatment-packages-deposits' ); ?></option>
                        <option value="percentage" <?php selected( $deposit_type, 'percentage' ); ?>><?php esc_html_e( 'Percentage', 'treatment-packages-deposits' ); ?></option>
                    </select>
                    <div class="tp-deposit-value-wrapper" style="<?php echo 'none' === $deposit_type ? 'display:none;' : ''; ?>">
                        <input
                            type="number"
                            name="tp_packages[<?php echo esc_attr( $index ); ?>][deposit_value]"
                            value="<?php echo esc_attr( $deposit_value ); ?>"
                            min="0"
                            step="0.01"
                            class="tp-input-deposit-value small-text"
                        >
                        <span class="tp-deposit-suffix"><?php echo 'percentage' === $deposit_type ? '%' : esc_html( $currency_symbol ); ?></span>
                    </div>
                </div>
            </td>

            <td class="tp-col-actions">
                <?php if ( $wc_product_id ) : ?>
                    <span class="tp-wc-linked dashicons dashicons-yes-alt" title="<?php esc_attr_e( 'Linked to WooCommerce product', 'treatment-packages-deposits' ); ?>"></span>
                <?php endif; ?>
                <button type="button" class="button-link tp-delete-package" title="<?php esc_attr_e( 'Delete package', 'treatment-packages-deposits' ); ?>">
                    <span class="dashicons dashicons-trash"></span>
                </button>
            </td>
        </tr>
        <?php
    }

    /**
     * Save packages data
     *
     * @param int      $post_id Post ID.
     * @param \WP_Post $post    Post object.
     */
    public static function save_packages( $post_id, $post ) {
        // Verify nonce
        if ( ! isset( $_POST['tp_packages_nonce'] ) ||
             ! wp_verify_nonce( $_POST['tp_packages_nonce'], 'tp_save_packages' ) ) {
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

        // Get submitted packages
        $submitted_packages = isset( $_POST['tp_packages'] ) ? $_POST['tp_packages'] : array();

        // Get existing packages
        $existing_packages = PackageRepository::get_by_treatment( $post_id );
        $existing_ids = array_map( function( $p ) {
            return $p->get_id();
        }, $existing_packages );

        // Track which IDs we're keeping
        $keep_ids = array();

        // Process submitted packages
        foreach ( $submitted_packages as $index => $data ) {
            $package = new PackageModel();

            // If existing package, load it first
            if ( ! empty( $data['id'] ) ) {
                $existing = PackageRepository::find( (int) $data['id'] );
                if ( $existing && $existing->get_treatment_id() === $post_id ) {
                    $package = $existing;
                    $keep_ids[] = $existing->get_id();
                }
            }

            // Update package data
            $package->set_treatment_id( $post_id );
            $package->set_sessions( isset( $data['sessions'] ) ? (int) $data['sessions'] : 1 );
            $package->set_name( isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '' );
            $package->set_total_price( isset( $data['total_price'] ) ? (float) $data['total_price'] : 0 );
            $package->set_deposit_type( isset( $data['deposit_type'] ) ? sanitize_text_field( $data['deposit_type'] ) : 'none' );
            $package->set_deposit_value( isset( $data['deposit_value'] ) ? (float) $data['deposit_value'] : 0 );
            $package->set_sort_order( isset( $data['sort_order'] ) ? (int) $data['sort_order'] : $index );

            // Save package
            PackageRepository::save( $package );
        }

        // Delete removed packages
        foreach ( $existing_ids as $existing_id ) {
            if ( ! in_array( $existing_id, $keep_ids, true ) ) {
                PackageRepository::delete( $existing_id );
            }
        }
    }

    /**
     * AJAX handler to get a new package row
     */
    public static function ajax_get_package_row() {
        check_ajax_referer( 'tp_admin_packages', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'treatment-packages-deposits' ) ) );
        }

        $index = isset( $_POST['index'] ) ? (int) $_POST['index'] : 0;

        ob_start();
        self::render_package_row( null, $index );
        $html = ob_get_clean();

        wp_send_json_success( array( 'html' => $html ) );
    }
}
