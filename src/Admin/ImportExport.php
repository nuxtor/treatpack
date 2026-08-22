<?php

namespace TreatmentPackages\Admin;

use TreatmentPackages\PostTypes\TreatmentPostType;
use TreatmentPackages\PostTypes\TreatmentTaxonomies;
use TreatmentPackages\Packages\PackageModel;
use TreatmentPackages\Packages\PackageRepository;

defined('ABSPATH') || exit;

/**
 * Import/Export Functionality
 *
 * Handles bulk import and export of treatments and packages
 */
class ImportExport {

    /**
     * Constructor
     */
    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_init', [$this, 'handle_export']);
        add_action('admin_init', [$this, 'handle_import']);
    }

    /**
     * Add admin menu
     */
    public function add_menu(): void {
        add_submenu_page(
            'edit.php?post_type=tp_treatment',
            __('Import / Export', 'treatpack'),
            __('Import / Export', 'treatpack'),
            'manage_options',
            'treatpack-import-export',
            [$this, 'render_page']
        );
    }

    /**
     * Enqueue scripts
     *
     * @param string $hook Current admin page
     */
    public function enqueue_scripts(string $hook): void {
        if ($hook !== 'tp_treatment_page_treatpack-import-export') {
            return;
        }

        wp_enqueue_style(
            'treatpack-admin-import-export',
            TREATPACK_PLUGIN_URL . 'assets/css/admin-import-export.css',
            [],
            TREATPACK_VERSION
        );
    }

    /**
     * Render admin page
     */
    public function render_page(): void {
        $message = '';
        $message_type = 'info';

        if (isset($_GET['exported'])) {
            $message = __('Export completed successfully.', 'treatpack');
            $message_type = 'success';
        }

        if (isset($_GET['imported'])) {
            $count = absint($_GET['imported']);
            $message = sprintf(
                _n('%d treatment imported successfully.', '%d treatments imported successfully.', $count, 'treatpack'),
                $count
            );
            $message_type = 'success';
        }

        if (isset($_GET['import_error'])) {
            $message = __('Error importing data. Please check your file format.', 'treatpack');
            $message_type = 'error';
        }
        ?>
        <div class="wrap treatpack-import-export">
            <h1><?php esc_html_e('Import / Export', 'treatpack'); ?></h1>

            <?php if ($message): ?>
                <div class="notice notice-<?php echo esc_attr($message_type); ?> is-dismissible">
                    <p><?php echo esc_html($message); ?></p>
                </div>
            <?php endif; ?>

            <div class="treatpack-import-export-panels">
                <!-- Export Panel -->
                <div class="treatpack-panel">
                    <h2><?php esc_html_e('Export', 'treatpack'); ?></h2>
                    <p><?php esc_html_e('Export all treatments and packages to a JSON file.', 'treatpack'); ?></p>

                    <form method="post">
                        <?php wp_nonce_field('treatpack_export', 'treatpack_export_nonce'); ?>

                        <p>
                            <label>
                                <input type="checkbox" name="include_categories" value="1" checked>
                                <?php esc_html_e('Include categories', 'treatpack'); ?>
                            </label>
                        </p>

                        <p>
                            <label>
                                <input type="checkbox" name="include_images" value="1">
                                <?php esc_html_e('Include image URLs', 'treatpack'); ?>
                            </label>
                        </p>

                        <button type="submit" name="treatpack_export" class="button button-primary">
                            <?php esc_html_e('Export Treatments', 'treatpack'); ?>
                        </button>
                    </form>
                </div>

                <!-- Import Panel -->
                <div class="treatpack-panel">
                    <h2><?php esc_html_e('Import', 'treatpack'); ?></h2>
                    <p><?php esc_html_e('Import treatments and packages from a JSON file.', 'treatpack'); ?></p>

                    <form method="post" enctype="multipart/form-data">
                        <?php wp_nonce_field('treatpack_import', 'treatpack_import_nonce'); ?>

                        <p>
                            <label for="import_file"><?php esc_html_e('Select JSON file:', 'treatpack'); ?></label><br>
                            <input type="file" name="import_file" id="import_file" accept=".json" required>
                        </p>

                        <p>
                            <label>
                                <input type="checkbox" name="update_existing" value="1">
                                <?php esc_html_e('Update existing treatments (match by title)', 'treatpack'); ?>
                            </label>
                        </p>

                        <p>
                            <label>
                                <input type="checkbox" name="import_categories" value="1" checked>
                                <?php esc_html_e('Import categories', 'treatpack'); ?>
                            </label>
                        </p>

                        <button type="submit" name="treatpack_import" class="button button-primary">
                            <?php esc_html_e('Import Treatments', 'treatpack'); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Sample Format -->
            <div class="treatpack-panel treatpack-sample-format">
                <h2><?php esc_html_e('JSON Format', 'treatpack'); ?></h2>
                <p><?php esc_html_e('Your import file should follow this structure:', 'treatpack'); ?></p>
                <pre><?php echo esc_html($this->get_sample_json()); ?></pre>
            </div>
        </div>
        <?php
    }

    /**
     * Handle export
     */
    public function handle_export(): void {
        if (!isset($_POST['treatpack_export']) || !isset($_POST['treatpack_export_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['treatpack_export_nonce'], 'treatpack_export')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        $include_categories = isset($_POST['include_categories']);
        $include_images = isset($_POST['include_images']);

        $data = $this->get_export_data($include_categories, $include_images);

        $filename = 'treatpack-export-' . date('Y-m-d-His') . '.json';

        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        echo wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Handle import
     */
    public function handle_import(): void {
        if (!isset($_POST['treatpack_import']) || !isset($_POST['treatpack_import_nonce'])) {
            return;
        }

        if (!wp_verify_nonce($_POST['treatpack_import_nonce'], 'treatpack_import')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            return;
        }

        if (!isset($_FILES['import_file']) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK) {
            wp_redirect(admin_url('edit.php?post_type=tp_treatment&page=treatpack-import-export&import_error=1'));
            exit;
        }

        $content = file_get_contents($_FILES['import_file']['tmp_name']);
        $data = json_decode($content, true);

        if (!$data || !isset($data['treatments'])) {
            wp_redirect(admin_url('edit.php?post_type=tp_treatment&page=treatpack-import-export&import_error=1'));
            exit;
        }

        $update_existing = isset($_POST['update_existing']);
        $import_categories = isset($_POST['import_categories']);

        $imported = $this->import_data($data, $update_existing, $import_categories);

        wp_redirect(admin_url('edit.php?post_type=tp_treatment&page=treatpack-import-export&imported=' . $imported));
        exit;
    }

    /**
     * Get export data
     *
     * @param bool $include_categories Include categories
     * @param bool $include_images     Include image URLs
     * @return array
     */
    private function get_export_data(bool $include_categories, bool $include_images): array {
        $treatments = get_posts([
            'post_type' => TreatmentPostType::POST_TYPE,
            'posts_per_page' => -1,
            'post_status' => 'any',
        ]);

        $export = [
            'version' => TREATPACK_VERSION,
            'exported_at' => current_time('mysql'),
            'treatments' => [],
        ];

        if ($include_categories) {
            $categories = get_terms([
                'taxonomy' => TreatmentTaxonomies::CATEGORY,
                'hide_empty' => false,
            ]);

            $export['categories'] = [];
            foreach ($categories as $cat) {
                $export['categories'][] = [
                    'name' => $cat->name,
                    'slug' => $cat->slug,
                    'description' => $cat->description,
                    'parent' => $cat->parent ? get_term($cat->parent)->slug : '',
                ];
            }
        }

        foreach ($treatments as $treatment) {
            $treatment_data = [
                'title' => $treatment->post_title,
                'content' => $treatment->post_content,
                'excerpt' => $treatment->post_excerpt,
                'status' => $treatment->post_status,
                'base_price' => get_post_meta($treatment->ID, '_treatpack_base_price', true),
                'duration' => get_post_meta($treatment->ID, '_treatpack_duration', true),
                'default_deposit_type' => get_post_meta($treatment->ID, '_treatpack_default_deposit_type', true),
                'default_deposit_amount' => get_post_meta($treatment->ID, '_treatpack_default_deposit_amount', true),
                'packages' => [],
            ];

            if ($include_categories) {
                $terms = wp_get_post_terms($treatment->ID, TreatmentTaxonomies::CATEGORY, ['fields' => 'slugs']);
                $treatment_data['categories'] = $terms;
            }

            if ($include_images && has_post_thumbnail($treatment->ID)) {
                $treatment_data['image_url'] = get_the_post_thumbnail_url($treatment->ID, 'full');
            }

            $packages = PackageRepository::get_by_treatment($treatment->ID, false);
            foreach ($packages as $package) {
                $treatment_data['packages'][] = [
                    'name' => $package->get_name(),
                    'sessions' => $package->get_sessions(),
                    'price' => $package->get_price(),
                    'sale_price' => $package->get_sale_price(),
                    'deposit_type' => $package->get_deposit_type(),
                    'deposit_amount' => $package->get_deposit_amount(),
                    'description' => $package->get_description(),
                    'is_active' => $package->is_active(),
                    'sort_order' => $package->get_sort_order(),
                ];
            }

            $export['treatments'][] = $treatment_data;
        }

        return $export;
    }

    /**
     * Import data
     *
     * @param array $data              Import data
     * @param bool  $update_existing   Update existing treatments
     * @param bool  $import_categories Import categories
     * @return int Number of imported treatments
     */
    private function import_data(array $data, bool $update_existing, bool $import_categories): int {
        $imported = 0;

        // Import categories first
        if ($import_categories && !empty($data['categories'])) {
            foreach ($data['categories'] as $cat) {
                $existing = get_term_by('slug', $cat['slug'], TreatmentTaxonomies::CATEGORY);

                if (!$existing) {
                    $parent_id = 0;
                    if (!empty($cat['parent'])) {
                        $parent = get_term_by('slug', $cat['parent'], TreatmentTaxonomies::CATEGORY);
                        if ($parent) {
                            $parent_id = $parent->term_id;
                        }
                    }

                    wp_insert_term($cat['name'], TreatmentTaxonomies::CATEGORY, [
                        'slug' => $cat['slug'],
                        'description' => $cat['description'] ?? '',
                        'parent' => $parent_id,
                    ]);
                }
            }
        }

        // Import treatments
        foreach ($data['treatments'] as $treatment_data) {
            $treatment_id = 0;

            // Check for existing
            if ($update_existing) {
                $existing = get_page_by_title($treatment_data['title'], OBJECT, TreatmentPostType::POST_TYPE);
                if ($existing) {
                    $treatment_id = $existing->ID;
                }
            }

            $post_data = [
                'post_title' => $treatment_data['title'],
                'post_content' => $treatment_data['content'] ?? '',
                'post_excerpt' => $treatment_data['excerpt'] ?? '',
                'post_status' => $treatment_data['status'] ?? 'publish',
                'post_type' => TreatmentPostType::POST_TYPE,
            ];

            if ($treatment_id) {
                $post_data['ID'] = $treatment_id;
                wp_update_post($post_data);
            } else {
                $treatment_id = wp_insert_post($post_data);
            }

            if (!$treatment_id || is_wp_error($treatment_id)) {
                continue;
            }

            // Update meta
            if (isset($treatment_data['base_price'])) {
                update_post_meta($treatment_id, '_treatpack_base_price', $treatment_data['base_price']);
            }
            if (isset($treatment_data['duration'])) {
                update_post_meta($treatment_id, '_treatpack_duration', $treatment_data['duration']);
            }
            if (isset($treatment_data['default_deposit_type'])) {
                update_post_meta($treatment_id, '_treatpack_default_deposit_type', $treatment_data['default_deposit_type']);
            }
            if (isset($treatment_data['default_deposit_amount'])) {
                update_post_meta($treatment_id, '_treatpack_default_deposit_amount', $treatment_data['default_deposit_amount']);
            }

            // Set categories
            if ($import_categories && !empty($treatment_data['categories'])) {
                wp_set_object_terms($treatment_id, $treatment_data['categories'], TreatmentTaxonomies::CATEGORY);
            }

            // Import packages
            if (!empty($treatment_data['packages'])) {
                // Delete existing packages if updating
                if ($update_existing) {
                    PackageRepository::delete_by_treatment($treatment_id);
                }

                foreach ($treatment_data['packages'] as $index => $pkg) {
                    $package = new PackageModel();
                    $package->set_treatment_id($treatment_id)
                        ->set_name($pkg['name'])
                        ->set_sessions($pkg['sessions'] ?? 1)
                        ->set_price($pkg['price'] ?? 0)
                        ->set_sale_price($pkg['sale_price'] ?? null)
                        ->set_deposit_type($pkg['deposit_type'] ?? 'none')
                        ->set_deposit_amount($pkg['deposit_amount'] ?? null)
                        ->set_description($pkg['description'] ?? null)
                        ->set_is_active($pkg['is_active'] ?? true)
                        ->set_sort_order($pkg['sort_order'] ?? $index);

                    $package_id = PackageRepository::create($package);

                    if ($package_id) {
                        $package->set_id($package_id);
                        do_action('treatpack_package_saved', $package);
                    }
                }
            }

            $imported++;
        }

        return $imported;
    }

    /**
     * Get sample JSON format
     *
     * @return string
     */
    private function get_sample_json(): string {
        return '{
  "version": "1.0.0",
  "categories": [
    {
      "name": "Facial Treatments",
      "slug": "facial-treatments",
      "description": "All facial treatments",
      "parent": ""
    }
  ],
  "treatments": [
    {
      "title": "Deep Cleansing Facial",
      "content": "A thorough cleansing treatment...",
      "excerpt": "Deep cleansing for all skin types",
      "status": "publish",
      "base_price": "75.00",
      "duration": "60",
      "categories": ["facial-treatments"],
      "packages": [
        {
          "name": "Single Session",
          "sessions": 1,
          "price": 75.00,
          "deposit_type": "none"
        },
        {
          "name": "5 Session Package",
          "sessions": 5,
          "price": 325.00,
          "sale_price": null,
          "deposit_type": "percentage",
          "deposit_amount": 20,
          "is_active": true
        }
      ]
    }
  ]
}';
    }
}
