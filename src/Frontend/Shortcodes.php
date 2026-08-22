<?php

namespace TreatmentPackages\Frontend;

use TreatmentPackages\PostTypes\TreatmentPostType;
use TreatmentPackages\PostTypes\TreatmentTaxonomies;
use TreatmentPackages\Packages\PackageRepository;

defined('ABSPATH') || exit;

/**
 * Frontend Shortcodes
 *
 * Provides shortcodes for displaying treatments and packages
 */
class Shortcodes {

    /**
     * Constructor
     */
    public function __construct() {
        add_shortcode('treatment_packages', [$this, 'treatment_packages']);
        add_shortcode('treatment_single', [$this, 'treatment_single']);
    }

    /**
     * [treatment_packages] - Display all treatments with optional category filter
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function treatment_packages(array $atts = []): string {
        $atts = shortcode_atts([
            'category' => '',
            'columns' => 3,
            'limit' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
            'show_categories' => 'yes',
            'show_excerpt' => 'yes',
            'show_price' => 'yes',
            'show_duration' => 'yes',
        ], $atts, 'treatment_packages');

        $args = [
            'post_type' => TreatmentPostType::POST_TYPE,
            'posts_per_page' => (int) $atts['limit'],
            'orderby' => $atts['orderby'],
            'order' => $atts['order'],
            'post_status' => 'publish',
        ];

        // Filter by category
        if (!empty($atts['category'])) {
            $args['tax_query'] = [
                [
                    'taxonomy' => TreatmentTaxonomies::CATEGORY,
                    'field' => is_numeric($atts['category']) ? 'term_id' : 'slug',
                    'terms' => $atts['category'],
                ],
            ];
        }

        $treatments = new \WP_Query($args);

        if (!$treatments->have_posts()) {
            return '<p class="treatpack-no-treatments">' . esc_html__('No treatments found.', 'treatpack') . '</p>';
        }

        ob_start();
        ?>
        <div class="treatpack-wrapper">
            <?php if ($atts['show_categories'] === 'yes'): ?>
                <?php $this->render_category_filter(); ?>
            <?php endif; ?>

            <div class="treatpack-treatments treatpack-columns-<?php echo esc_attr($atts['columns']); ?>">
                <?php while ($treatments->have_posts()): $treatments->the_post(); ?>
                    <?php $this->render_treatment_card(get_the_ID(), $atts); ?>
                <?php endwhile; ?>
            </div>
        </div>
        <?php
        wp_reset_postdata();

        return ob_get_clean();
    }

    /**
     * [treatment_single] - Display a single treatment or multiple by ID
     *
     * @param array $atts Shortcode attributes
     * @return string
     */
    public function treatment_single(array $atts = []): string {
        $atts = shortcode_atts([
            'id' => '',
            'show_excerpt' => 'yes',
            'show_description' => 'yes',
            'show_price' => 'yes',
            'show_duration' => 'yes',
            'show_image' => 'yes',
        ], $atts, 'treatment_single');

        if (empty($atts['id'])) {
            return '';
        }

        $ids = array_map('absint', explode(',', $atts['id']));

        ob_start();
        ?>
        <div class="treatpack-single-treatments">
            <?php foreach ($ids as $id): ?>
                <?php
                $treatment = get_post($id);
                if (!$treatment || $treatment->post_type !== TreatmentPostType::POST_TYPE) {
                    continue;
                }
                ?>
                <?php $this->render_treatment_single($treatment, $atts); ?>
            <?php endforeach; ?>
        </div>
        <?php

        return ob_get_clean();
    }

    /**
     * Render category filter sidebar
     */
    private function render_category_filter(): void {
        $categories = TreatmentTaxonomies::get_categories(['hide_empty' => true]);

        if (empty($categories)) {
            return;
        }
        ?>
        <div class="treatpack-category-sidebar">
            <h4><?php esc_html_e('Categories', 'treatpack'); ?></h4>
            <ul class="treatpack-category-list">
                <li>
                    <a href="#" class="treatpack-category-filter active" data-category="all">
                        <?php esc_html_e('All Treatments', 'treatpack'); ?>
                    </a>
                </li>
                <?php foreach ($categories as $category): ?>
                    <li>
                        <a href="#" class="treatpack-category-filter" data-category="<?php echo esc_attr($category->term_id); ?>">
                            <?php echo esc_html($category->name); ?>
                            <span class="count">(<?php echo esc_html($category->count); ?>)</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * Render a treatment card
     *
     * @param int   $treatment_id Treatment post ID
     * @param array $atts         Display options
     */
    private function render_treatment_card(int $treatment_id, array $atts): void {
        $packages = PackageRepository::get_by_treatment($treatment_id);
        $base_price = get_post_meta($treatment_id, '_treatpack_base_price', true);
        $duration = get_post_meta($treatment_id, '_treatpack_duration', true);
        $categories = wp_get_post_terms($treatment_id, TreatmentTaxonomies::CATEGORY, ['fields' => 'ids']);
        ?>
        <div class="treatpack-treatment-card" data-categories="<?php echo esc_attr(implode(',', $categories)); ?>">
            <?php if (has_post_thumbnail($treatment_id)): ?>
                <div class="treatpack-treatment-image-wrap">
                    <?php echo get_the_post_thumbnail($treatment_id, 'medium', ['class' => 'treatpack-treatment-image']); ?>
                </div>
            <?php endif; ?>

            <div class="treatpack-treatment-content">
                <h3 class="treatpack-treatment-title">
                    <a href="<?php echo esc_url(get_permalink($treatment_id)); ?>">
                        <?php echo esc_html(get_the_title($treatment_id)); ?>
                    </a>
                </h3>

                <?php if ($atts['show_excerpt'] === 'yes' && has_excerpt($treatment_id)): ?>
                    <div class="treatpack-treatment-excerpt">
                        <?php echo wp_kses_post(get_the_excerpt($treatment_id)); ?>
                    </div>
                <?php endif; ?>

                <div class="treatpack-treatment-meta">
                    <?php if ($atts['show_price'] === 'yes' && $base_price): ?>
                        <div class="treatpack-treatment-price">
                            <?php echo sprintf(esc_html__('From %s', 'treatpack'), wc_price($base_price)); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($atts['show_duration'] === 'yes' && $duration): ?>
                        <div class="treatpack-treatment-duration">
                            <span class="dashicons dashicons-clock"></span>
                            <?php echo sprintf(esc_html(_n('%d minute', '%d minutes', $duration, 'treatpack')), $duration); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($packages)): ?>
                    <div class="treatpack-packages">
                        <select class="treatpack-package-select">
                            <option value=""><?php esc_html_e('Select a package...', 'treatpack'); ?></option>
                            <?php foreach ($packages as $package): ?>
                                <option value="<?php echo esc_attr($package->get_id()); ?>"
                                        data-price="<?php echo esc_attr(wc_price($package->get_active_price())); ?>"
                                        data-sessions="<?php echo esc_attr($package->get_sessions()); ?>">
                                    <?php
                                    echo esc_html(sprintf(
                                        '%s - %d %s - %s',
                                        $package->get_name(),
                                        $package->get_sessions(),
                                        _n('session', 'sessions', $package->get_sessions(), 'treatpack'),
                                        strip_tags(wc_price($package->get_active_price()))
                                    ));
                                    ?>
                                </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="treatpack-selected-price"></div>

                        <button type="button" class="treatpack-add-to-cart" disabled>
                            <?php esc_html_e('Add to Cart', 'treatpack'); ?>
                        </button>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render a single treatment (full view)
     *
     * @param \WP_Post $treatment Treatment post
     * @param array    $atts      Display options
     */
    private function render_treatment_single(\WP_Post $treatment, array $atts): void {
        $packages = PackageRepository::get_by_treatment($treatment->ID);
        $base_price = get_post_meta($treatment->ID, '_treatpack_base_price', true);
        $duration = get_post_meta($treatment->ID, '_treatpack_duration', true);
        ?>
        <div class="treatpack-treatment-single">
            <?php if ($atts['show_image'] === 'yes' && has_post_thumbnail($treatment->ID)): ?>
                <div class="treatpack-treatment-image-wrap">
                    <?php echo get_the_post_thumbnail($treatment->ID, 'large', ['class' => 'treatpack-treatment-image']); ?>
                </div>
            <?php endif; ?>

            <div class="treatpack-treatment-details">
                <h2 class="treatpack-treatment-title"><?php echo esc_html($treatment->post_title); ?></h2>

                <div class="treatpack-treatment-meta">
                    <?php if ($atts['show_price'] === 'yes' && $base_price): ?>
                        <div class="treatpack-treatment-price">
                            <strong><?php esc_html_e('Starting at:', 'treatpack'); ?></strong>
                            <?php echo wc_price($base_price); ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($atts['show_duration'] === 'yes' && $duration): ?>
                        <div class="treatpack-treatment-duration">
                            <strong><?php esc_html_e('Duration:', 'treatpack'); ?></strong>
                            <?php echo sprintf(esc_html(_n('%d minute', '%d minutes', $duration, 'treatpack')), $duration); ?>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($atts['show_description'] === 'yes' && !empty($treatment->post_content)): ?>
                    <div class="treatpack-treatment-description">
                        <?php echo wp_kses_post(apply_filters('the_content', $treatment->post_content)); ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($packages)): ?>
                    <div class="treatpack-packages-table">
                        <h3><?php esc_html_e('Available Packages', 'treatpack'); ?></h3>
                        <table>
                            <thead>
                                <tr>
                                    <th><?php esc_html_e('Package', 'treatpack'); ?></th>
                                    <th><?php esc_html_e('Sessions', 'treatpack'); ?></th>
                                    <th><?php esc_html_e('Price', 'treatpack'); ?></th>
                                    <th><?php esc_html_e('Per Session', 'treatpack'); ?></th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($packages as $package): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($package->get_name()); ?></strong>
                                            <?php if ($package->get_description()): ?>
                                                <br><small><?php echo esc_html($package->get_description()); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo esc_html($package->get_sessions()); ?></td>
                                        <td>
                                            <?php echo wc_price($package->get_active_price()); ?>
                                            <?php if ($package->get_sale_price()): ?>
                                                <del><?php echo wc_price($package->get_price()); ?></del>
                                            <?php endif; ?>
                                        </td>
                                        <td><?php echo wc_price($package->get_price_per_session()); ?></td>
                                        <td>
                                            <button type="button"
                                                    class="treatpack-add-to-cart-btn button"
                                                    data-package-id="<?php echo esc_attr($package->get_id()); ?>">
                                                <?php esc_html_e('Add to Cart', 'treatpack'); ?>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }
}
