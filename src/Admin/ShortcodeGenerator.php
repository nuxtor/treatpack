<?php
/**
 * Shortcode Generator Admin Page
 *
 * @package TreatmentPackages\Admin
 */

namespace TreatmentPackages\Admin;

use TreatmentPackages\PostTypes\TreatmentPostType;
use TreatmentPackages\PostTypes\TreatmentTaxonomies;
use TreatmentPackages\Packages\PackageRepository;

defined( 'ABSPATH' ) || exit;

/**
 * ShortcodeGenerator Class
 *
 * Provides an admin page for visually building treatment shortcodes.
 */
class ShortcodeGenerator {

    /**
     * Initialize shortcode generator
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_action( 'wp_ajax_tp_get_shortcode_data', array( __CLASS__, 'ajax_get_shortcode_data' ) );
    }

    /**
     * Register admin menu
     */
    public static function register_menu() {
        add_submenu_page(
            'edit.php?post_type=' . TreatmentPostType::POST_TYPE,
            __( 'Shortcode Generator', 'treatpack' ),
            __( 'Shortcode Generator', 'treatpack' ),
            'manage_woocommerce',
            'tp-shortcode-generator',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_assets( $hook ) {
        if ( 'treatment_page_tp-shortcode-generator' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'tp-shortcode-generator',
            TP_DEPOSITS_PLUGIN_URL . 'assets/css/admin-shortcode-generator.css',
            array(),
            TP_DEPOSITS_VERSION
        );

        wp_enqueue_script(
            'tp-shortcode-generator',
            TP_DEPOSITS_PLUGIN_URL . 'assets/js/admin-shortcode-generator.js',
            array( 'jquery' ),
            TP_DEPOSITS_VERSION,
            true
        );

        wp_localize_script(
            'tp-shortcode-generator',
            'tpShortcodeGen',
            array(
                'ajaxUrl' => admin_url( 'admin-ajax.php' ),
                'nonce'   => wp_create_nonce( 'tp_shortcode_generator' ),
                'i18n'    => array(
                    'copied'       => __( 'Copied!', 'treatpack' ),
                    'copyFailed'   => __( 'Copy failed. Please select and copy manually.', 'treatpack' ),
                    'loading'      => __( 'Loading treatments...', 'treatpack' ),
                    'noTreatments' => __( 'No treatments found.', 'treatpack' ),
                ),
            )
        );
    }

    /**
     * AJAX: Get categories and treatments data
     */
    public static function ajax_get_shortcode_data() {
        check_ajax_referer( 'tp_shortcode_generator', 'nonce' );

        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            wp_send_json_error( array( 'message' => __( 'Permission denied.', 'treatpack' ) ) );
        }

        // Get all categories
        $categories = get_terms( array(
            'taxonomy'   => TreatmentTaxonomies::CATEGORY_TAXONOMY,
            'hide_empty' => false,
        ) );

        $categories_data = array();
        if ( ! is_wp_error( $categories ) ) {
            foreach ( $categories as $cat ) {
                $categories_data[] = array(
                    'id'   => $cat->term_id,
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                );
            }
        }

        // Get all published treatments
        $treatments = get_posts( array(
            'post_type'      => TreatmentPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        $treatments_data = array();
        foreach ( $treatments as $treatment ) {
            $terms = get_the_terms( $treatment->ID, TreatmentTaxonomies::CATEGORY_TAXONOMY );
            $cat_slugs = array();
            if ( $terms && ! is_wp_error( $terms ) ) {
                $cat_slugs = wp_list_pluck( $terms, 'slug' );
            }

            $treatments_data[] = array(
                'id'         => $treatment->ID,
                'title'      => $treatment->post_title,
                'categories' => $cat_slugs,
            );
        }

        wp_send_json_success( array(
            'categories' => $categories_data,
            'treatments' => $treatments_data,
        ) );
    }

    /**
     * Render the shortcode generator page
     */
    public static function render_page() {
        ?>
        <div class="wrap tp-shortcode-generator-wrap">
            <h1 class="wp-heading-inline">
                <?php esc_html_e( 'Shortcode Generator', 'treatpack' ); ?>
            </h1>
            <hr class="wp-header-end">

            <p class="tp-sg-description">
                <?php esc_html_e( 'Select a shortcode type, configure the options, then copy the generated shortcode to use on any page or post.', 'treatpack' ); ?>
            </p>

            <!-- Shortcode Type Selector -->
            <div class="tp-sg-type-selector">
                <label class="tp-sg-type-card active" data-type="treatment_packages">
                    <input type="radio" name="tp_sg_type" value="treatment_packages" checked>
                    <span class="tp-sg-type-icon dashicons dashicons-grid-view"></span>
                    <span class="tp-sg-type-title"><?php esc_html_e( 'Treatment Packages Grid', 'treatpack' ); ?></span>
                    <span class="tp-sg-type-desc"><?php esc_html_e( 'Display multiple treatments with category sidebar, filtering, and package dropdowns.', 'treatpack' ); ?></span>
                </label>
                <label class="tp-sg-type-card" data-type="treatment_single">
                    <input type="radio" name="tp_sg_type" value="treatment_single">
                    <span class="tp-sg-type-icon dashicons dashicons-id-alt"></span>
                    <span class="tp-sg-type-title"><?php esc_html_e( 'Single Treatment', 'treatpack' ); ?></span>
                    <span class="tp-sg-type-desc"><?php esc_html_e( 'Display one or more specific treatments by ID.', 'treatpack' ); ?></span>
                </label>
            </div>

            <div class="tp-sg-main-layout">

                <!-- Options Panel -->
                <div class="tp-sg-options-panel">

                    <!-- treatment_packages options -->
                    <div class="tp-sg-options-group" id="tp-sg-options-packages">
                        <h2><?php esc_html_e( 'Treatment Packages Grid Options', 'treatpack' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label for="tp-sg-category"><?php esc_html_e( 'Categories', 'treatpack' ); ?></label>
                                </th>
                                <td>
                                    <select id="tp-sg-category" multiple class="tp-sg-multi-select">
                                        <option value="" disabled><?php esc_html_e( 'Loading...', 'treatpack' ); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'Select categories to display. Leave empty to show all.', 'treatpack' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e( 'Specific Treatments', 'treatpack' ); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="tp-sg-pkg-ids" class="regular-text" readonly placeholder="<?php esc_attr_e( 'Click "Browse Treatments" to select', 'treatpack' ); ?>">
                                    <button type="button" class="button tp-sg-browse-btn" data-target="tp-sg-pkg-ids">
                                        <?php esc_html_e( 'Browse Treatments', 'treatpack' ); ?>
                                    </button>
                                    <button type="button" class="button tp-sg-clear-ids-btn" data-target="tp-sg-pkg-ids" style="display:none;">
                                        <?php esc_html_e( 'Clear', 'treatpack' ); ?>
                                    </button>
                                    <p class="description"><?php esc_html_e( 'Optional. Select specific treatments by ID. Overrides category filter.', 'treatpack' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="tp-sg-pkg-columns"><?php esc_html_e( 'Columns', 'treatpack' ); ?></label>
                                </th>
                                <td>
                                    <select id="tp-sg-pkg-columns">
                                        <option value="2">2</option>
                                        <option value="3" selected>3</option>
                                        <option value="4">4</option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="tp-sg-show-sidebar"><?php esc_html_e( 'Show Sidebar', 'treatpack' ); ?></label>
                                </th>
                                <td>
                                    <select id="tp-sg-show-sidebar">
                                        <option value="yes" selected><?php esc_html_e( 'Yes', 'treatpack' ); ?></option>
                                        <option value="no"><?php esc_html_e( 'No', 'treatpack' ); ?></option>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'Show the category sidebar for filtering.', 'treatpack' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="tp-sg-show-intro"><?php esc_html_e( 'Show Intro Text', 'treatpack' ); ?></label>
                                </th>
                                <td>
                                    <select id="tp-sg-show-intro">
                                        <option value="yes" selected><?php esc_html_e( 'Yes', 'treatpack' ); ?></option>
                                        <option value="no"><?php esc_html_e( 'No', 'treatpack' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr id="tp-sg-intro-text-row">
                                <th scope="row">
                                    <label for="tp-sg-intro-text"><?php esc_html_e( 'Intro Text', 'treatpack' ); ?></label>
                                </th>
                                <td>
                                    <textarea id="tp-sg-intro-text" class="large-text" rows="3"><?php esc_html_e( 'To purchase a treatment package, select the number of sessions and click to add it to the shopping basket.', 'treatpack' ); ?></textarea>
                                    <p class="description"><?php esc_html_e( 'Introductory text displayed above the grid.', 'treatpack' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="tp-sg-orderby"><?php esc_html_e( 'Order By', 'treatpack' ); ?></label>
                                </th>
                                <td>
                                    <select id="tp-sg-orderby">
                                        <option value="menu_order" selected><?php esc_html_e( 'Menu Order', 'treatpack' ); ?></option>
                                        <option value="title"><?php esc_html_e( 'Title', 'treatpack' ); ?></option>
                                        <option value="date"><?php esc_html_e( 'Date', 'treatpack' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="tp-sg-order"><?php esc_html_e( 'Order', 'treatpack' ); ?></label>
                                </th>
                                <td>
                                    <select id="tp-sg-order">
                                        <option value="ASC" selected><?php esc_html_e( 'Ascending', 'treatpack' ); ?></option>
                                        <option value="DESC"><?php esc_html_e( 'Descending', 'treatpack' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                        </table>
                    </div>

                    <!-- treatment_single options -->
                    <div class="tp-sg-options-group" id="tp-sg-options-single" style="display:none;">
                        <h2><?php esc_html_e( 'Single Treatment Options', 'treatpack' ); ?></h2>
                        <table class="form-table">
                            <tr>
                                <th scope="row">
                                    <label><?php esc_html_e( 'Treatment(s)', 'treatpack' ); ?></label>
                                </th>
                                <td>
                                    <input type="text" id="tp-sg-single-ids" class="regular-text" readonly placeholder="<?php esc_attr_e( 'Click "Browse Treatments" to select', 'treatpack' ); ?>">
                                    <button type="button" class="button tp-sg-browse-btn" data-target="tp-sg-single-ids">
                                        <?php esc_html_e( 'Browse Treatments', 'treatpack' ); ?>
                                    </button>
                                    <button type="button" class="button tp-sg-clear-ids-btn" data-target="tp-sg-single-ids" style="display:none;">
                                        <?php esc_html_e( 'Clear', 'treatpack' ); ?>
                                    </button>
                                    <p class="description"><?php esc_html_e( 'Required. Select one or more treatments to display.', 'treatpack' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">
                                    <label for="tp-sg-single-columns"><?php esc_html_e( 'Columns', 'treatpack' ); ?></label>
                                </th>
                                <td>
                                    <select id="tp-sg-single-columns">
                                        <option value="2">2</option>
                                        <option value="3" selected>3</option>
                                        <option value="4">4</option>
                                    </select>
                                    <p class="description"><?php esc_html_e( 'Only applies when multiple treatments are selected.', 'treatpack' ); ?></p>
                                </td>
                            </tr>
                        </table>
                    </div>

                </div>

                <!-- Output Panel -->
                <div class="tp-sg-output-panel">
                    <h2><?php esc_html_e( 'Generated Shortcode', 'treatpack' ); ?></h2>
                    <div class="tp-sg-output-area">
                        <input type="text" id="tp-sg-output" class="tp-sg-output-field" value="[treatment_packages]" readonly>
                        <button type="button" id="tp-sg-copy-btn" class="button button-primary">
                            <span class="dashicons dashicons-clipboard"></span>
                            <?php esc_html_e( 'Copy', 'treatpack' ); ?>
                        </button>
                    </div>
                    <p class="tp-sg-output-hint">
                        <?php esc_html_e( 'Paste this shortcode into any page or post to display treatments.', 'treatpack' ); ?>
                    </p>
                </div>

            </div>

            <!-- Treatment Browser Modal -->
            <div id="tp-sg-browser-modal" class="tp-sg-modal" style="display:none;">
                <div class="tp-sg-modal-content">
                    <div class="tp-sg-modal-header">
                        <h3><?php esc_html_e( 'Browse Treatments', 'treatpack' ); ?></h3>
                        <button type="button" class="tp-sg-modal-close">&times;</button>
                    </div>
                    <div class="tp-sg-modal-body">
                        <input type="text" id="tp-sg-browser-search" class="widefat" placeholder="<?php esc_attr_e( 'Search treatments...', 'treatpack' ); ?>">
                        <div id="tp-sg-browser-list" class="tp-sg-browser-list">
                            <p class="tp-sg-loading"><?php esc_html_e( 'Loading...', 'treatpack' ); ?></p>
                        </div>
                    </div>
                    <div class="tp-sg-modal-footer">
                        <span id="tp-sg-browser-selected-count">0 <?php esc_html_e( 'selected', 'treatpack' ); ?></span>
                        <button type="button" class="button button-primary" id="tp-sg-browser-apply">
                            <?php esc_html_e( 'Apply Selection', 'treatpack' ); ?>
                        </button>
                        <button type="button" class="button" id="tp-sg-browser-cancel">
                            <?php esc_html_e( 'Cancel', 'treatpack' ); ?>
                        </button>
                    </div>
                </div>
            </div>

        </div>
        <?php
    }
}
