<?php
/**
 * Frontend Shortcodes
 *
 * @package TreatmentPackages\Frontend
 */

namespace TreatmentPackages\Frontend;

use TreatmentPackages\PostTypes\TreatmentPostType;
use TreatmentPackages\PostTypes\TreatmentTaxonomies;
use TreatmentPackages\Packages\PackageRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Shortcodes Class
 *
 * Registers and renders frontend shortcodes for displaying treatment packages.
 */
class Shortcodes {

    /**
     * Initialize shortcodes
     */
    public static function init() {
        add_shortcode( 'treatment_packages', array( __CLASS__, 'render_treatment_packages' ) );
        add_shortcode( 'treatment_single', array( __CLASS__, 'render_single_treatment' ) );

        // AJAX handler for category filtering
        add_action( 'wp_ajax_tp_filter_treatments', array( __CLASS__, 'ajax_filter_treatments' ) );
        add_action( 'wp_ajax_nopriv_tp_filter_treatments', array( __CLASS__, 'ajax_filter_treatments' ) );
    }

    /**
     * Render treatment packages shortcode
     *
     * Usage:
     *   [treatment_packages category="women-packages" columns="3"]
     *   [treatment_packages category="women-packages,men-packages" columns="3"]
     *   [treatment_packages ids="123,456,789" columns="3"]
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function render_treatment_packages( $atts ) {
        $atts = shortcode_atts( array(
            'category'       => '',
            'ids'            => '',
            'columns'        => 3,
            'show_sidebar'   => 'yes',
            'show_intro'     => 'yes',
            'intro_text'     => __( 'To purchase a treatment package, select the number of sessions and click to add it to the shopping basket.', 'treatment-packages-deposits' ),
            'orderby'        => 'menu_order',
            'order'          => 'ASC',
        ), $atts, 'treatment_packages' );

        // Enqueue assets
        wp_enqueue_style( 'tp-deposits-frontend' );
        wp_enqueue_script( 'tp-deposits-frontend' );

        // Parse multiple categories
        $category_slugs = array();
        if ( ! empty( $atts['category'] ) ) {
            $category_slugs = array_map( 'trim', explode( ',', $atts['category'] ) );
            $category_slugs = array_filter( $category_slugs );
        }

        // Parse multiple treatment IDs
        $treatment_ids = array();
        if ( ! empty( $atts['ids'] ) ) {
            $treatment_ids = array_map( 'absint', explode( ',', $atts['ids'] ) );
            $treatment_ids = array_filter( $treatment_ids );
        }

        // Get categories for sidebar (only relevant categories if specific ones are requested)
        $sidebar_categories = array();
        if ( 'yes' === $atts['show_sidebar'] ) {
            if ( ! empty( $category_slugs ) ) {
                // Get only the specified categories for sidebar
                $sidebar_categories = get_terms( array(
                    'taxonomy'   => TreatmentTaxonomies::CATEGORY_TAXONOMY,
                    'slug'       => $category_slugs,
                    'hide_empty' => true,
                ) );
            } else {
                $sidebar_categories = TreatmentTaxonomies::get_categories( array( 'hide_empty' => true ) );
            }
        }

        // Determine active category for highlighting in sidebar
        $active_category = '';
        if ( ! empty( $category_slugs ) ) {
            $active_category = $category_slugs[0]; // First category is active by default
        } elseif ( ! empty( $sidebar_categories ) && ! is_wp_error( $sidebar_categories ) ) {
            $active_category = $sidebar_categories[0]->slug;
        }

        // Get treatments based on IDs or categories
        if ( ! empty( $treatment_ids ) ) {
            // Get specific treatments by IDs
            $treatments = self::get_treatments_by_ids( $treatment_ids, $atts );
        } elseif ( ! empty( $category_slugs ) ) {
            // Get ALL treatments from specified categories so JavaScript can filter them
            $treatments = self::get_treatments_by_categories( $category_slugs, $atts );
        } else {
            // Get ALL treatments
            $treatments = self::get_treatments( '', $atts );
        }

        // Determine if we should show sidebar (hide if using IDs or single category)
        $show_sidebar = 'yes' === $atts['show_sidebar'] &&
                        empty( $treatment_ids ) &&
                        ! empty( $sidebar_categories ) &&
                        ! is_wp_error( $sidebar_categories ) &&
                        count( $sidebar_categories ) > 1;

        ob_start();
        ?>
        <style>
            /* Hide cards not in active category on initial load */
            <?php if ( ! empty( $active_category ) && $show_sidebar ) : ?>
            .tp-packages-wrapper .tp-package-card:not([data-category*="<?php echo esc_attr( $active_category ); ?>"]) {
                display: none;
            }
            <?php endif; ?>
        </style>
        <div class="tp-packages-wrapper" data-columns="<?php echo esc_attr( $atts['columns'] ); ?>" data-active-category="<?php echo esc_attr( $active_category ); ?>">

            <?php if ( 'yes' === $atts['show_intro'] && ! empty( $atts['intro_text'] ) ) : ?>
                <p class="tp-packages-intro">
                    <?php echo esc_html( $atts['intro_text'] ); ?>
                </p>
            <?php endif; ?>

            <div class="tp-packages-container <?php echo ! $show_sidebar ? 'no-sidebar' : ''; ?>">

                <?php if ( $show_sidebar ) : ?>
                    <aside class="tp-categories-sidebar">
                        <ul class="tp-categories-list">
                            <?php foreach ( $sidebar_categories as $category ) : ?>
                                <li class="tp-category-item <?php echo $active_category === $category->slug ? 'active' : ''; ?>">
                                    <a href="#" class="tp-category-link" data-category="<?php echo esc_attr( $category->slug ); ?>">
                                        <?php echo esc_html( $category->name ); ?>
                                        <span class="dashicons dashicons-arrow-right-alt2"></span>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </aside>
                <?php endif; ?>

                <div class="tp-packages-grid">
                    <?php if ( ! empty( $treatments ) ) : ?>
                        <?php foreach ( $treatments as $treatment ) : ?>
                            <?php self::render_treatment_card( $treatment ); ?>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <p class="tp-no-treatments">
                            <?php esc_html_e( 'No treatments found.', 'treatment-packages-deposits' ); ?>
                        </p>
                    <?php endif; ?>
                </div>

            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Render single treatment shortcode
     *
     * Usage:
     *   [treatment_single id="123"]
     *   [treatment_single id="123,456,789" columns="3"]
     *
     * @param array $atts Shortcode attributes.
     * @return string HTML output.
     */
    public static function render_single_treatment( $atts ) {
        $atts = shortcode_atts( array(
            'id'      => '',
            'columns' => 3,
        ), $atts, 'treatment_single' );

        if ( empty( $atts['id'] ) ) {
            return '';
        }

        // Parse multiple IDs
        $treatment_ids = array_map( 'absint', explode( ',', $atts['id'] ) );
        $treatment_ids = array_filter( $treatment_ids );

        if ( empty( $treatment_ids ) ) {
            return '';
        }

        // Enqueue assets
        wp_enqueue_style( 'tp-deposits-frontend' );
        wp_enqueue_script( 'tp-deposits-frontend' );

        // Get treatments
        $treatments = get_posts( array(
            'post_type'      => TreatmentPostType::POST_TYPE,
            'post__in'       => $treatment_ids,
            'posts_per_page' => count( $treatment_ids ),
            'orderby'        => 'post__in',
            'post_status'    => 'publish',
        ) );

        if ( empty( $treatments ) ) {
            return '';
        }

        $is_multiple = count( $treatments ) > 1;

        ob_start();
        ?>
        <div class="tp-single-treatment <?php echo $is_multiple ? 'tp-multiple-treatments' : ''; ?>" data-columns="<?php echo esc_attr( $atts['columns'] ); ?>">
            <?php if ( $is_multiple ) : ?>
                <div class="tp-packages-grid">
                    <?php foreach ( $treatments as $treatment ) : ?>
                        <?php self::render_treatment_card( $treatment ); ?>
                    <?php endforeach; ?>
                </div>
            <?php else : ?>
                <?php self::render_treatment_card( $treatments[0] ); ?>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * Get treatments based on category and attributes
     *
     * @param string $category Category slug.
     * @param array  $atts     Shortcode attributes.
     * @return array Array of treatment posts.
     */
    private static function get_treatments( $category, $atts ) {
        $args = array(
            'post_type'      => TreatmentPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => $atts['orderby'],
            'order'          => $atts['order'],
        );

        if ( ! empty( $category ) ) {
            $args['tax_query'] = array(
                array(
                    'taxonomy' => TreatmentTaxonomies::CATEGORY_TAXONOMY,
                    'field'    => 'slug',
                    'terms'    => $category,
                ),
            );
        }

        $query = new \WP_Query( $args );

        return $query->posts;
    }

    /**
     * Get treatments by specific IDs
     *
     * @param array $ids  Array of treatment post IDs.
     * @param array $atts Shortcode attributes.
     * @return array Array of treatment posts.
     */
    private static function get_treatments_by_ids( $ids, $atts ) {
        if ( empty( $ids ) ) {
            return array();
        }

        $args = array(
            'post_type'      => TreatmentPostType::POST_TYPE,
            'post_status'    => 'publish',
            'post__in'       => $ids,
            'posts_per_page' => count( $ids ),
            'orderby'        => 'post__in', // Preserve the order specified in IDs
        );

        // Allow overriding the order
        if ( isset( $atts['orderby'] ) && 'post__in' !== $atts['orderby'] ) {
            $args['orderby'] = $atts['orderby'];
            $args['order'] = $atts['order'];
        }

        $query = new \WP_Query( $args );

        return $query->posts;
    }

    /**
     * Get treatments by multiple categories
     *
     * @param array $categories Array of category slugs.
     * @param array $atts       Shortcode attributes.
     * @return array Array of treatment posts.
     */
    private static function get_treatments_by_categories( $categories, $atts ) {
        if ( empty( $categories ) ) {
            return array();
        }

        $args = array(
            'post_type'      => TreatmentPostType::POST_TYPE,
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => $atts['orderby'],
            'order'          => $atts['order'],
            'tax_query'      => array(
                array(
                    'taxonomy' => TreatmentTaxonomies::CATEGORY_TAXONOMY,
                    'field'    => 'slug',
                    'terms'    => $categories,
                    'operator' => 'IN',
                ),
            ),
        );

        $query = new \WP_Query( $args );

        return $query->posts;
    }

    /**
     * Render a treatment card
     *
     * @param \WP_Post $treatment Treatment post object.
     */
    private static function render_treatment_card( $treatment ) {
        $packages = PackageRepository::get_by_treatment( $treatment->ID );

        if ( empty( $packages ) ) {
            return;
        }

        // Get min price and max discount
        $min_price = PackageRepository::get_min_per_session_price( $treatment->ID );
        $max_discount = PackageRepository::get_max_discount( $treatment->ID );

        // Get treatment categories for data attribute
        $terms = get_the_terms( $treatment->ID, TreatmentTaxonomies::CATEGORY_TAXONOMY );
        $category_slugs = array();
        if ( $terms && ! is_wp_error( $terms ) ) {
            $category_slugs = wp_list_pluck( $terms, 'slug' );
        }

        $currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '£';
        ?>
        <div class="tp-package-card" data-treatment-id="<?php echo esc_attr( $treatment->ID ); ?>" data-category="<?php echo esc_attr( implode( ' ', $category_slugs ) ); ?>">

            <?php if ( $max_discount > 0 ) : ?>
                <span class="tp-discount-badge">
                    <?php
                    printf(
                        /* translators: %s: discount percentage */
                        esc_html__( 'UP TO %s%% OFF', 'treatment-packages-deposits' ),
                        number_format( $max_discount, 0 )
                    );
                    ?>
                </span>
            <?php endif; ?>

            <h3 class="tp-package-title"><?php echo esc_html( $treatment->post_title ); ?></h3>

            <div class="tp-package-price">
                <span class="price-from"><?php esc_html_e( 'From only', 'treatment-packages-deposits' ); ?></span>
                <span class="price-amount"><?php echo esc_html( $currency_symbol . number_format( $min_price, 2 ) ); ?></span>
                <span class="price-per"><?php esc_html_e( 'per session', 'treatment-packages-deposits' ); ?></span>
            </div>

            <div class="tp-session-dropdown">
                <button type="button" class="tp-dropdown-toggle">
                    <span><?php esc_html_e( 'SELECT AND SAVE', 'treatment-packages-deposits' ); ?></span>
                    <span class="dropdown-arrow dashicons dashicons-arrow-down-alt2"></span>
                </button>

                <div class="tp-dropdown-options">
                    <?php foreach ( $packages as $package ) : ?>
                        <?php self::render_package_option( $package ); ?>
                    <?php endforeach; ?>
                </div>
            </div>

        </div>
        <?php
    }

    /**
     * Render a package option in the dropdown
     *
     * @param \TreatmentPackages\Packages\PackageModel $package Package model.
     */
    private static function render_package_option( $package ) {
        $wc_product_id = $package->get_wc_product_id();

        if ( ! $wc_product_id ) {
            return;
        }

        $currency_symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '£';

        // Calculate original price (based on base price * sessions)
        $base_price = get_post_meta( $package->get_treatment_id(), '_tp_base_price', true );
        $original_price = $base_price ? floatval( $base_price ) * $package->get_sessions() : 0;

        // Get display name with discount
        $display_name = $package->get_display_name();
        $discount = $package->get_discount_percent();

        if ( $discount > 0 ) {
            $display_name .= ' - ' . number_format( $discount, 0 ) . '% off';
        }
        ?>
        <div class="tp-session-option" data-product-id="<?php echo esc_attr( $wc_product_id ); ?>" data-package-id="<?php echo esc_attr( $package->get_id() ); ?>">
            <div class="tp-option-info">
                <div class="tp-option-name"><?php echo esc_html( $display_name ); ?></div>
                <div class="tp-option-pricing">
                    <?php if ( $original_price > 0 && $original_price > $package->get_total_price() ) : ?>
                        <span class="original-price"><?php echo esc_html( $currency_symbol . number_format( $original_price, 2 ) ); ?></span>
                    <?php endif; ?>
                    <span class="sale-price"><?php echo esc_html( $currency_symbol . number_format( $package->get_total_price(), 2 ) ); ?></span>
                </div>
                <?php if ( $package->get_sessions() > 1 ) : ?>
                    <div class="tp-option-per-session">
                        <?php esc_html_e( 'Only', 'treatment-packages-deposits' ); ?>
                        <span class="amount"><?php echo esc_html( number_format( $package->get_per_session_price(), 2 ) ); ?></span>
                        <?php esc_html_e( 'per session', 'treatment-packages-deposits' ); ?>
                    </div>
                <?php endif; ?>
            </div>
            <button type="button" class="tp-order-btn">+ ORDER</button>
        </div>
        <?php
    }

    /**
     * AJAX handler to filter treatments by category
     */
    public static function ajax_filter_treatments() {
        check_ajax_referer( 'tp_deposits_nonce', 'nonce' );

        $category = isset( $_POST['category'] ) ? sanitize_text_field( $_POST['category'] ) : '';

        $treatments = self::get_treatments( $category, array(
            'orderby' => 'menu_order',
            'order'   => 'ASC',
        ) );

        ob_start();

        if ( ! empty( $treatments ) ) {
            foreach ( $treatments as $treatment ) {
                self::render_treatment_card( $treatment );
            }
        } else {
            ?>
            <p class="tp-no-treatments">
                <?php esc_html_e( 'No treatments found in this category.', 'treatment-packages-deposits' ); ?>
            </p>
            <?php
        }

        $html = ob_get_clean();

        wp_send_json_success( array( 'html' => $html ) );
    }
}
