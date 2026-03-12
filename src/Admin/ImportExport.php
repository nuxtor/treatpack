<?php
/**
 * Import/Export Functionality
 *
 * @package TreatmentPackages\Admin
 */

namespace TreatmentPackages\Admin;

use TreatmentPackages\PostTypes\TreatmentPostType;
use TreatmentPackages\PostTypes\TreatmentTaxonomies;
use TreatmentPackages\Packages\PackageRepository;
use TreatmentPackages\Packages\PackageModel;

defined( 'ABSPATH' ) || exit;

/**
 * ImportExport Class
 *
 * Handles export and import of treatments and packages.
 */
class ImportExport {

    /**
     * Export file version for compatibility
     *
     * @var string
     */
    const EXPORT_VERSION = '1.0';

    /**
     * Initialize import/export functionality
     */
    public static function init() {
        add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_export' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_csv_export' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_import' ) );
        add_action( 'admin_init', array( __CLASS__, 'handle_cleanup' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
        add_filter( 'upload_mimes', array( __CLASS__, 'allow_json_upload' ) );
    }

    /**
     * Allow JSON file uploads for import
     *
     * @param array $mimes Allowed mime types.
     * @return array
     */
    public static function allow_json_upload( $mimes ) {
        $mimes['json'] = 'application/json';
        return $mimes;
    }

    /**
     * Register admin menu
     */
    public static function register_menu() {
        add_submenu_page(
            'edit.php?post_type=' . TreatmentPostType::POST_TYPE,
            __( 'Import / Export', 'treatment-packages-deposits' ),
            __( 'Import / Export', 'treatment-packages-deposits' ),
            'manage_options',
            'tp-import-export',
            array( __CLASS__, 'render_page' )
        );
    }

    /**
     * Enqueue admin assets
     *
     * @param string $hook Current admin page hook.
     */
    public static function enqueue_assets( $hook ) {
        if ( 'treatment_page_tp-import-export' !== $hook ) {
            return;
        }

        wp_enqueue_style(
            'tp-import-export',
            TP_DEPOSITS_PLUGIN_URL . 'assets/css/admin-import-export.css',
            array(),
            TP_DEPOSITS_VERSION
        );
    }

    /**
     * Render the import/export page
     */
    public static function render_page() {
        // Check for messages
        $message = '';
        $message_type = '';

        if ( isset( $_GET['exported'] ) && $_GET['exported'] === '1' ) {
            $message = __( 'Export completed successfully.', 'treatment-packages-deposits' );
            $message_type = 'success';
        }

        if ( isset( $_GET['imported'] ) ) {
            $imported = absint( $_GET['imported'] );
            $message = sprintf(
                /* translators: %d: number of treatments imported */
                __( 'Import completed! %d treatments imported/updated.', 'treatment-packages-deposits' ),
                $imported
            );
            $message_type = 'success';
        }

        if ( isset( $_GET['import_error'] ) ) {
            $message = urldecode( $_GET['import_error'] );
            $message_type = 'error';
        }

        if ( isset( $_GET['cleanup_done'] ) ) {
            $deleted = absint( $_GET['deleted'] ?? 0 );
            $updated = absint( $_GET['updated'] ?? 0 );
            $message = sprintf(
                /* translators: %1$d: deleted count, %2$d: updated count */
                __( 'Cleanup completed! %1$d single session packages removed, %2$d packages updated with base price.', 'treatment-packages-deposits' ),
                $deleted,
                $updated
            );
            $message_type = 'success';
        }

        // Get counts for display
        $treatment_count = wp_count_posts( TreatmentPostType::POST_TYPE );
        $total_treatments = isset( $treatment_count->publish ) ? $treatment_count->publish : 0;
        $total_packages = self::count_packages();
        ?>
        <div class="wrap tp-import-export-wrap">
            <h1><?php esc_html_e( 'Import / Export Treatments', 'treatment-packages-deposits' ); ?></h1>

            <?php if ( $message ) : ?>
                <div class="notice notice-<?php echo esc_attr( $message_type ); ?> is-dismissible">
                    <p><?php echo esc_html( $message ); ?></p>
                </div>
            <?php endif; ?>

            <div class="tp-ie-grid">
                <!-- Export Section -->
                <div class="tp-ie-card tp-export-card">
                    <h2><?php esc_html_e( 'Export Treatments', 'treatment-packages-deposits' ); ?></h2>
                    <p><?php esc_html_e( 'Export all treatments and their pricing packages to a JSON file.', 'treatment-packages-deposits' ); ?></p>

                    <div class="tp-ie-stats">
                        <div class="tp-ie-stat">
                            <span class="tp-ie-stat-number"><?php echo esc_html( $total_treatments ); ?></span>
                            <span class="tp-ie-stat-label"><?php esc_html_e( 'Treatments', 'treatment-packages-deposits' ); ?></span>
                        </div>
                        <div class="tp-ie-stat">
                            <span class="tp-ie-stat-number"><?php echo esc_html( $total_packages ); ?></span>
                            <span class="tp-ie-stat-label"><?php esc_html_e( 'Packages', 'treatment-packages-deposits' ); ?></span>
                        </div>
                    </div>

                    <form method="post" action="">
                        <?php wp_nonce_field( 'tp_export_treatments', 'tp_export_nonce' ); ?>

                        <div class="tp-ie-options">
                            <label>
                                <input type="checkbox" name="include_categories" value="1" checked>
                                <?php esc_html_e( 'Include categories', 'treatment-packages-deposits' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="include_areas" value="1" checked>
                                <?php esc_html_e( 'Include body areas', 'treatment-packages-deposits' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="include_images" value="1" checked>
                                <?php esc_html_e( 'Include image URLs', 'treatment-packages-deposits' ); ?>
                            </label>
                        </div>

                        <button type="submit" name="tp_export" class="button button-primary button-large">
                            <?php esc_html_e( 'Download JSON Export', 'treatment-packages-deposits' ); ?>
                        </button>
                    </form>
                </div>

                <!-- CSV Export Section -->
                <div class="tp-ie-card tp-csv-export-card">
                    <h2><?php esc_html_e( 'Export to CSV', 'treatment-packages-deposits' ); ?></h2>
                    <p><?php esc_html_e( 'Export treatments or packages to CSV format for use in Excel, Google Sheets, etc.', 'treatment-packages-deposits' ); ?></p>

                    <form method="post" action="">
                        <?php wp_nonce_field( 'tp_csv_export_treatments', 'tp_csv_export_nonce' ); ?>

                        <div class="tp-ie-options">
                            <label class="tp-ie-radio">
                                <input type="radio" name="csv_export_type" value="treatments" checked>
                                <?php esc_html_e( 'Treatments Summary', 'treatment-packages-deposits' ); ?>
                                <span class="tp-ie-radio-desc"><?php esc_html_e( 'One row per treatment with stats', 'treatment-packages-deposits' ); ?></span>
                            </label>
                            <label class="tp-ie-radio">
                                <input type="radio" name="csv_export_type" value="packages">
                                <?php esc_html_e( 'All Packages', 'treatment-packages-deposits' ); ?>
                                <span class="tp-ie-radio-desc"><?php esc_html_e( 'One row per package with full pricing details', 'treatment-packages-deposits' ); ?></span>
                            </label>
                        </div>

                        <button type="submit" name="tp_csv_export" class="button button-primary button-large">
                            <?php esc_html_e( 'Download CSV', 'treatment-packages-deposits' ); ?>
                        </button>
                    </form>
                </div>

                <!-- Import Section -->
                <div class="tp-ie-card tp-import-card">
                    <h2><?php esc_html_e( 'Import Treatments', 'treatment-packages-deposits' ); ?></h2>
                    <p><?php esc_html_e( 'Import treatments and packages from a JSON export file.', 'treatment-packages-deposits' ); ?></p>

                    <form method="post" action="" enctype="multipart/form-data">
                        <?php wp_nonce_field( 'tp_import_treatments', 'tp_import_nonce' ); ?>

                        <div class="tp-ie-file-upload">
                            <label for="tp_import_file"><?php esc_html_e( 'Select JSON file:', 'treatment-packages-deposits' ); ?></label>
                            <input type="file" name="tp_import_file" id="tp_import_file" accept=".json" required>
                        </div>

                        <div class="tp-ie-options">
                            <label>
                                <input type="checkbox" name="update_existing" value="1" checked>
                                <?php esc_html_e( 'Update existing treatments (matched by title)', 'treatment-packages-deposits' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="import_categories" value="1" checked>
                                <?php esc_html_e( 'Import categories', 'treatment-packages-deposits' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="import_areas" value="1" checked>
                                <?php esc_html_e( 'Import body areas', 'treatment-packages-deposits' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="skip_existing_packages" value="1">
                                <?php esc_html_e( 'Skip packages for existing treatments', 'treatment-packages-deposits' ); ?>
                            </label>
                        </div>

                        <div class="tp-ie-warning">
                            <strong><?php esc_html_e( 'Note:', 'treatment-packages-deposits' ); ?></strong>
                            <?php esc_html_e( 'Importing will create new treatments or update existing ones. Make sure to backup your database before importing.', 'treatment-packages-deposits' ); ?>
                        </div>

                        <button type="submit" name="tp_import" class="button button-primary button-large">
                            <?php esc_html_e( 'Import Treatments', 'treatment-packages-deposits' ); ?>
                        </button>
                    </form>
                </div>
            </div>

            <!-- Cleanup Section -->
            <?php
            $single_session_count = self::count_single_session_packages();
            $zero_price_count = self::count_zero_price_packages();
            ?>
            <div class="tp-ie-card tp-cleanup-card">
                <h2><?php esc_html_e( 'Package Cleanup', 'treatment-packages-deposits' ); ?></h2>
                <p><?php esc_html_e( 'Remove single session packages (use base price instead) and fix packages with £0.00 price.', 'treatment-packages-deposits' ); ?></p>

                <div class="tp-ie-stats">
                    <div class="tp-ie-stat">
                        <span class="tp-ie-stat-number"><?php echo esc_html( $single_session_count ); ?></span>
                        <span class="tp-ie-stat-label"><?php esc_html_e( 'Single Session Packages', 'treatment-packages-deposits' ); ?></span>
                    </div>
                    <div class="tp-ie-stat">
                        <span class="tp-ie-stat-number"><?php echo esc_html( $zero_price_count ); ?></span>
                        <span class="tp-ie-stat-label"><?php esc_html_e( 'Packages with £0.00', 'treatment-packages-deposits' ); ?></span>
                    </div>
                </div>

                <?php if ( $single_session_count > 0 || $zero_price_count > 0 ) : ?>
                    <form method="post" action="">
                        <?php wp_nonce_field( 'tp_cleanup_packages', 'tp_cleanup_nonce' ); ?>

                        <div class="tp-ie-options">
                            <label>
                                <input type="checkbox" name="delete_single_sessions" value="1" checked>
                                <?php esc_html_e( 'Delete single session packages (sessions = 1)', 'treatment-packages-deposits' ); ?>
                            </label>
                            <label>
                                <input type="checkbox" name="fix_zero_prices" value="1" checked>
                                <?php esc_html_e( 'Update £0.00 packages with treatment base price × sessions', 'treatment-packages-deposits' ); ?>
                            </label>
                        </div>

                        <div class="tp-ie-warning">
                            <strong><?php esc_html_e( 'Warning:', 'treatment-packages-deposits' ); ?></strong>
                            <?php esc_html_e( 'This action cannot be undone. Make sure to backup your database or export your data first.', 'treatment-packages-deposits' ); ?>
                        </div>

                        <button type="submit" name="tp_cleanup" class="button button-primary button-large">
                            <?php esc_html_e( 'Run Cleanup', 'treatment-packages-deposits' ); ?>
                        </button>
                    </form>
                <?php else : ?>
                    <p class="tp-ie-success-msg">
                        <span class="dashicons dashicons-yes-alt"></span>
                        <?php esc_html_e( 'No cleanup needed. All packages are properly configured.', 'treatment-packages-deposits' ); ?>
                    </p>
                <?php endif; ?>
            </div>

            <!-- Export Format Info -->
            <div class="tp-ie-card tp-info-card">
                <h2><?php esc_html_e( 'Export Format', 'treatment-packages-deposits' ); ?></h2>
                <p><?php esc_html_e( 'The export file contains:', 'treatment-packages-deposits' ); ?></p>
                <ul>
                    <li><?php esc_html_e( 'Treatment categories with hierarchy', 'treatment-packages-deposits' ); ?></li>
                    <li><?php esc_html_e( 'Treatment body areas', 'treatment-packages-deposits' ); ?></li>
                    <li><?php esc_html_e( 'All treatments with title, content, excerpt, and settings', 'treatment-packages-deposits' ); ?></li>
                    <li><?php esc_html_e( 'All pricing packages with sessions, prices, and deposit settings', 'treatment-packages-deposits' ); ?></li>
                </ul>
                <p><strong><?php esc_html_e( 'Note:', 'treatment-packages-deposits' ); ?></strong> <?php esc_html_e( 'WooCommerce product IDs are not exported as they need to be regenerated on import via product sync.', 'treatment-packages-deposits' ); ?></p>
            </div>
        </div>
        <?php
    }

    /**
     * Handle export request
     */
    public static function handle_export() {
        if ( ! isset( $_POST['tp_export'] ) ) {
            return;
        }

        if ( ! isset( $_POST['tp_export_nonce'] ) || ! wp_verify_nonce( $_POST['tp_export_nonce'], 'tp_export_treatments' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $include_categories = isset( $_POST['include_categories'] );
        $include_areas = isset( $_POST['include_areas'] );
        $include_images = isset( $_POST['include_images'] );

        $export_data = self::generate_export_data( $include_categories, $include_areas, $include_images );

        // Set headers for download
        $filename = 'treatments-export-' . date( 'Y-m-d-His' ) . '.json';

        header( 'Content-Type: application/json; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        echo wp_json_encode( $export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
        exit;
    }

    /**
     * Handle CSV export request
     */
    public static function handle_csv_export() {
        if ( ! isset( $_POST['tp_csv_export'] ) ) {
            return;
        }

        if ( ! isset( $_POST['tp_csv_export_nonce'] ) || ! wp_verify_nonce( $_POST['tp_csv_export_nonce'], 'tp_csv_export_treatments' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $export_type = isset( $_POST['csv_export_type'] ) ? sanitize_text_field( $_POST['csv_export_type'] ) : 'treatments';

        if ( $export_type === 'packages' ) {
            self::export_packages_csv();
        } else {
            self::export_treatments_csv();
        }
    }

    /**
     * Export treatments to CSV
     */
    private static function export_treatments_csv() {
        $filename = 'treatments-export-' . date( 'Y-m-d-His' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // Add BOM for Excel UTF-8 compatibility
        fprintf( $output, chr(0xEF) . chr(0xBB) . chr(0xBF) );

        // CSV Header
        fputcsv( $output, array(
            'ID',
            'Title',
            'Slug',
            'Status',
            'Categories',
            'Body Areas',
            'Base Price',
            'Default Deposit Type',
            'Default Deposit Value',
            'Total Packages',
            'Min Price',
            'Max Price',
            'Max Discount %',
            'Excerpt',
        ) );

        // Get treatments
        $treatments = get_posts( array(
            'post_type'      => TreatmentPostType::POST_TYPE,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        foreach ( $treatments as $treatment ) {
            // Get categories
            $categories = array();
            $treatment_categories = get_the_terms( $treatment->ID, TreatmentTaxonomies::CATEGORY_TAXONOMY );
            if ( $treatment_categories && ! is_wp_error( $treatment_categories ) ) {
                foreach ( $treatment_categories as $cat ) {
                    $categories[] = $cat->name;
                }
            }

            // Get areas
            $areas = array();
            $treatment_areas = get_the_terms( $treatment->ID, TreatmentTaxonomies::AREA_TAXONOMY );
            if ( $treatment_areas && ! is_wp_error( $treatment_areas ) ) {
                foreach ( $treatment_areas as $area ) {
                    $areas[] = $area->name;
                }
            }

            // Get packages stats
            $packages = PackageRepository::get_by_treatment( $treatment->ID );
            $package_count = count( $packages );
            $min_price = 0;
            $max_price = 0;
            $max_discount = 0;

            if ( $package_count > 0 ) {
                $prices = array();
                foreach ( $packages as $package ) {
                    $prices[] = $package->get_total_price();
                    $discount = $package->get_discount_percent();
                    if ( $discount > $max_discount ) {
                        $max_discount = $discount;
                    }
                }
                $min_price = min( $prices );
                $max_price = max( $prices );
            }

            fputcsv( $output, array(
                $treatment->ID,
                $treatment->post_title,
                $treatment->post_name,
                $treatment->post_status,
                implode( ', ', $categories ),
                implode( ', ', $areas ),
                get_post_meta( $treatment->ID, '_tp_base_price', true ),
                get_post_meta( $treatment->ID, '_tp_default_deposit_type', true ),
                get_post_meta( $treatment->ID, '_tp_default_deposit_value', true ),
                $package_count,
                $min_price,
                $max_price,
                $max_discount,
                $treatment->post_excerpt,
            ) );
        }

        fclose( $output );
        exit;
    }

    /**
     * Export packages to CSV
     */
    private static function export_packages_csv() {
        $filename = 'packages-export-' . date( 'Y-m-d-His' ) . '.csv';

        header( 'Content-Type: text/csv; charset=utf-8' );
        header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
        header( 'Pragma: no-cache' );
        header( 'Expires: 0' );

        $output = fopen( 'php://output', 'w' );

        // Add BOM for Excel UTF-8 compatibility
        fprintf( $output, chr(0xEF) . chr(0xBB) . chr(0xBF) );

        // CSV Header
        fputcsv( $output, array(
            'Package ID',
            'Treatment ID',
            'Treatment Name',
            'Package Name',
            'Sessions',
            'Total Price',
            'Per Session Price',
            'Discount %',
            'Deposit Type',
            'Deposit Value',
            'Deposit Amount',
            'WC Product ID',
            'Sort Order',
        ) );

        // Get all treatments
        $treatments = get_posts( array(
            'post_type'      => TreatmentPostType::POST_TYPE,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        foreach ( $treatments as $treatment ) {
            $packages = PackageRepository::get_by_treatment( $treatment->ID );

            foreach ( $packages as $package ) {
                // Calculate deposit amount
                $deposit_amount = 0;
                if ( $package->get_deposit_type() === 'fixed' ) {
                    $deposit_amount = $package->get_deposit_value();
                } elseif ( $package->get_deposit_type() === 'percentage' ) {
                    $deposit_amount = ( $package->get_total_price() * $package->get_deposit_value() ) / 100;
                }

                fputcsv( $output, array(
                    $package->get_id(),
                    $treatment->ID,
                    $treatment->post_title,
                    $package->get_name(),
                    $package->get_sessions(),
                    $package->get_total_price(),
                    $package->get_per_session_price(),
                    $package->get_discount_percent(),
                    $package->get_deposit_type(),
                    $package->get_deposit_value(),
                    round( $deposit_amount, 2 ),
                    $package->get_wc_product_id(),
                    $package->get_sort_order(),
                ) );
            }
        }

        fclose( $output );
        exit;
    }

    /**
     * Generate export data
     *
     * @param bool $include_categories Include categories in export.
     * @param bool $include_areas      Include areas in export.
     * @param bool $include_images     Include image URLs in export.
     * @return array Export data.
     */
    private static function generate_export_data( $include_categories = true, $include_areas = true, $include_images = true ) {
        $export = array(
            'version'      => self::EXPORT_VERSION,
            'exported_at'  => current_time( 'mysql' ),
            'site_url'     => get_site_url(),
            'categories'   => array(),
            'areas'        => array(),
            'treatments'   => array(),
        );

        // Export categories
        if ( $include_categories ) {
            $categories = get_terms( array(
                'taxonomy'   => TreatmentTaxonomies::CATEGORY_TAXONOMY,
                'hide_empty' => false,
            ) );

            if ( ! is_wp_error( $categories ) ) {
                foreach ( $categories as $category ) {
                    $parent_slug = '';
                    if ( $category->parent > 0 ) {
                        $parent_term = get_term( $category->parent, TreatmentTaxonomies::CATEGORY_TAXONOMY );
                        if ( $parent_term && ! is_wp_error( $parent_term ) ) {
                            $parent_slug = $parent_term->slug;
                        }
                    }

                    $export['categories'][] = array(
                        'name'        => $category->name,
                        'slug'        => $category->slug,
                        'description' => $category->description,
                        'parent_slug' => $parent_slug,
                    );
                }
            }
        }

        // Export areas
        if ( $include_areas ) {
            $areas = get_terms( array(
                'taxonomy'   => TreatmentTaxonomies::AREA_TAXONOMY,
                'hide_empty' => false,
            ) );

            if ( ! is_wp_error( $areas ) ) {
                foreach ( $areas as $area ) {
                    $export['areas'][] = array(
                        'name'        => $area->name,
                        'slug'        => $area->slug,
                        'description' => $area->description,
                    );
                }
            }
        }

        // Export treatments
        $treatments = get_posts( array(
            'post_type'      => TreatmentPostType::POST_TYPE,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ) );

        foreach ( $treatments as $treatment ) {
            $treatment_data = array(
                'title'        => $treatment->post_title,
                'slug'         => $treatment->post_name,
                'content'      => $treatment->post_content,
                'excerpt'      => $treatment->post_excerpt,
                'status'       => $treatment->post_status,
                'menu_order'   => $treatment->menu_order,
                'meta'         => array(
                    'base_price'            => get_post_meta( $treatment->ID, '_tp_base_price', true ),
                    'default_deposit_type'  => get_post_meta( $treatment->ID, '_tp_default_deposit_type', true ),
                    'default_deposit_value' => get_post_meta( $treatment->ID, '_tp_default_deposit_value', true ),
                ),
                'categories'   => array(),
                'areas'        => array(),
                'packages'     => array(),
            );

            // Get featured image
            if ( $include_images ) {
                $thumbnail_id = get_post_thumbnail_id( $treatment->ID );
                if ( $thumbnail_id ) {
                    $treatment_data['featured_image'] = wp_get_attachment_url( $thumbnail_id );
                }
            }

            // Get categories
            $treatment_categories = get_the_terms( $treatment->ID, TreatmentTaxonomies::CATEGORY_TAXONOMY );
            if ( $treatment_categories && ! is_wp_error( $treatment_categories ) ) {
                foreach ( $treatment_categories as $cat ) {
                    $treatment_data['categories'][] = $cat->slug;
                }
            }

            // Get areas
            $treatment_areas = get_the_terms( $treatment->ID, TreatmentTaxonomies::AREA_TAXONOMY );
            if ( $treatment_areas && ! is_wp_error( $treatment_areas ) ) {
                foreach ( $treatment_areas as $area ) {
                    $treatment_data['areas'][] = $area->slug;
                }
            }

            // Get packages
            $packages = PackageRepository::get_by_treatment( $treatment->ID );
            foreach ( $packages as $package ) {
                $treatment_data['packages'][] = array(
                    'name'          => $package->get_name(),
                    'sessions'      => $package->get_sessions(),
                    'total_price'   => $package->get_total_price(),
                    'deposit_type'  => $package->get_deposit_type(),
                    'deposit_value' => $package->get_deposit_value(),
                    'sort_order'    => $package->get_sort_order(),
                );
            }

            $export['treatments'][] = $treatment_data;
        }

        return $export;
    }

    /**
     * Handle import request
     */
    public static function handle_import() {
        if ( ! isset( $_POST['tp_import'] ) ) {
            return;
        }

        if ( ! isset( $_POST['tp_import_nonce'] ) || ! wp_verify_nonce( $_POST['tp_import_nonce'], 'tp_import_treatments' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Check file upload
        if ( ! isset( $_FILES['tp_import_file'] ) || $_FILES['tp_import_file']['error'] !== UPLOAD_ERR_OK ) {
            self::redirect_with_error( __( 'File upload failed. Please try again.', 'treatment-packages-deposits' ) );
            return;
        }

        $file = $_FILES['tp_import_file'];

        // Validate file type
        $file_info = wp_check_filetype( $file['name'] );
        if ( $file_info['ext'] !== 'json' ) {
            self::redirect_with_error( __( 'Invalid file type. Please upload a JSON file.', 'treatment-packages-deposits' ) );
            return;
        }

        // Read and parse JSON
        $json_content = file_get_contents( $file['tmp_name'] );
        $import_data = json_decode( $json_content, true );

        if ( json_last_error() !== JSON_ERROR_NONE ) {
            self::redirect_with_error( __( 'Invalid JSON file. Please check the file format.', 'treatment-packages-deposits' ) );
            return;
        }

        // Validate import data structure
        if ( ! isset( $import_data['treatments'] ) || ! is_array( $import_data['treatments'] ) ) {
            self::redirect_with_error( __( 'Invalid export file format. Missing treatments data.', 'treatment-packages-deposits' ) );
            return;
        }

        // Get options
        $update_existing = isset( $_POST['update_existing'] );
        $import_categories = isset( $_POST['import_categories'] );
        $import_areas = isset( $_POST['import_areas'] );
        $skip_existing_packages = isset( $_POST['skip_existing_packages'] );

        // Perform import
        $imported_count = self::perform_import(
            $import_data,
            $update_existing,
            $import_categories,
            $import_areas,
            $skip_existing_packages
        );

        // Redirect with success
        wp_redirect( add_query_arg(
            array(
                'post_type' => TreatmentPostType::POST_TYPE,
                'page'      => 'tp-import-export',
                'imported'  => $imported_count,
            ),
            admin_url( 'edit.php' )
        ) );
        exit;
    }

    /**
     * Perform the import
     *
     * @param array $data                   Import data.
     * @param bool  $update_existing        Update existing treatments.
     * @param bool  $import_categories      Import categories.
     * @param bool  $import_areas           Import areas.
     * @param bool  $skip_existing_packages Skip packages for existing treatments.
     * @return int Number of treatments imported/updated.
     */
    private static function perform_import( $data, $update_existing, $import_categories, $import_areas, $skip_existing_packages ) {
        $imported_count = 0;
        $category_map = array(); // slug => term_id
        $area_map = array();     // slug => term_id

        // Import categories first
        if ( $import_categories && ! empty( $data['categories'] ) ) {
            // First pass: create categories without parents
            foreach ( $data['categories'] as $category ) {
                if ( empty( $category['parent_slug'] ) ) {
                    $term = term_exists( $category['slug'], TreatmentTaxonomies::CATEGORY_TAXONOMY );
                    if ( ! $term ) {
                        $result = wp_insert_term(
                            $category['name'],
                            TreatmentTaxonomies::CATEGORY_TAXONOMY,
                            array(
                                'slug'        => $category['slug'],
                                'description' => $category['description'] ?? '',
                            )
                        );
                        if ( ! is_wp_error( $result ) ) {
                            $category_map[ $category['slug'] ] = $result['term_id'];
                        }
                    } else {
                        $category_map[ $category['slug'] ] = is_array( $term ) ? $term['term_id'] : $term;
                    }
                }
            }

            // Second pass: create categories with parents
            foreach ( $data['categories'] as $category ) {
                if ( ! empty( $category['parent_slug'] ) ) {
                    $term = term_exists( $category['slug'], TreatmentTaxonomies::CATEGORY_TAXONOMY );
                    if ( ! $term ) {
                        $parent_id = isset( $category_map[ $category['parent_slug'] ] ) ? $category_map[ $category['parent_slug'] ] : 0;
                        $result = wp_insert_term(
                            $category['name'],
                            TreatmentTaxonomies::CATEGORY_TAXONOMY,
                            array(
                                'slug'        => $category['slug'],
                                'description' => $category['description'] ?? '',
                                'parent'      => $parent_id,
                            )
                        );
                        if ( ! is_wp_error( $result ) ) {
                            $category_map[ $category['slug'] ] = $result['term_id'];
                        }
                    } else {
                        $category_map[ $category['slug'] ] = is_array( $term ) ? $term['term_id'] : $term;
                    }
                }
            }
        }

        // Import areas
        if ( $import_areas && ! empty( $data['areas'] ) ) {
            foreach ( $data['areas'] as $area ) {
                $term = term_exists( $area['slug'], TreatmentTaxonomies::AREA_TAXONOMY );
                if ( ! $term ) {
                    $result = wp_insert_term(
                        $area['name'],
                        TreatmentTaxonomies::AREA_TAXONOMY,
                        array(
                            'slug'        => $area['slug'],
                            'description' => $area['description'] ?? '',
                        )
                    );
                    if ( ! is_wp_error( $result ) ) {
                        $area_map[ $area['slug'] ] = $result['term_id'];
                    }
                } else {
                    $area_map[ $area['slug'] ] = is_array( $term ) ? $term['term_id'] : $term;
                }
            }
        }

        // Build category and area maps for existing terms
        $all_categories = get_terms( array(
            'taxonomy'   => TreatmentTaxonomies::CATEGORY_TAXONOMY,
            'hide_empty' => false,
        ) );
        if ( ! is_wp_error( $all_categories ) ) {
            foreach ( $all_categories as $cat ) {
                $category_map[ $cat->slug ] = $cat->term_id;
            }
        }

        $all_areas = get_terms( array(
            'taxonomy'   => TreatmentTaxonomies::AREA_TAXONOMY,
            'hide_empty' => false,
        ) );
        if ( ! is_wp_error( $all_areas ) ) {
            foreach ( $all_areas as $area ) {
                $area_map[ $area->slug ] = $area->term_id;
            }
        }

        // Import treatments
        foreach ( $data['treatments'] as $treatment_data ) {
            $existing_treatment = self::find_treatment_by_title( $treatment_data['title'] );
            $is_existing = (bool) $existing_treatment;
            $treatment_id = null;

            if ( $existing_treatment && ! $update_existing ) {
                // Skip existing treatment
                continue;
            }

            if ( $existing_treatment && $update_existing ) {
                // Update existing treatment
                $treatment_id = $existing_treatment->ID;

                wp_update_post( array(
                    'ID'           => $treatment_id,
                    'post_content' => $treatment_data['content'] ?? '',
                    'post_excerpt' => $treatment_data['excerpt'] ?? '',
                    'menu_order'   => $treatment_data['menu_order'] ?? 0,
                ) );
            } else {
                // Create new treatment
                $treatment_id = wp_insert_post( array(
                    'post_type'    => TreatmentPostType::POST_TYPE,
                    'post_title'   => $treatment_data['title'],
                    'post_name'    => $treatment_data['slug'] ?? '',
                    'post_content' => $treatment_data['content'] ?? '',
                    'post_excerpt' => $treatment_data['excerpt'] ?? '',
                    'post_status'  => $treatment_data['status'] ?? 'publish',
                    'menu_order'   => $treatment_data['menu_order'] ?? 0,
                ) );
            }

            if ( ! $treatment_id || is_wp_error( $treatment_id ) ) {
                continue;
            }

            // Update meta
            if ( ! empty( $treatment_data['meta'] ) ) {
                if ( isset( $treatment_data['meta']['base_price'] ) ) {
                    update_post_meta( $treatment_id, '_tp_base_price', $treatment_data['meta']['base_price'] );
                }
                if ( isset( $treatment_data['meta']['default_deposit_type'] ) ) {
                    update_post_meta( $treatment_id, '_tp_default_deposit_type', $treatment_data['meta']['default_deposit_type'] );
                }
                if ( isset( $treatment_data['meta']['default_deposit_value'] ) ) {
                    update_post_meta( $treatment_id, '_tp_default_deposit_value', $treatment_data['meta']['default_deposit_value'] );
                }
            }

            // Set categories
            if ( ! empty( $treatment_data['categories'] ) ) {
                $category_ids = array();
                foreach ( $treatment_data['categories'] as $cat_slug ) {
                    if ( isset( $category_map[ $cat_slug ] ) ) {
                        $category_ids[] = (int) $category_map[ $cat_slug ];
                    }
                }
                if ( ! empty( $category_ids ) ) {
                    wp_set_object_terms( $treatment_id, $category_ids, TreatmentTaxonomies::CATEGORY_TAXONOMY );
                }
            }

            // Set areas
            if ( ! empty( $treatment_data['areas'] ) ) {
                $area_ids = array();
                foreach ( $treatment_data['areas'] as $area_slug ) {
                    if ( isset( $area_map[ $area_slug ] ) ) {
                        $area_ids[] = (int) $area_map[ $area_slug ];
                    }
                }
                if ( ! empty( $area_ids ) ) {
                    wp_set_object_terms( $treatment_id, $area_ids, TreatmentTaxonomies::AREA_TAXONOMY );
                }
            }

            // Import featured image from URL
            if ( ! empty( $treatment_data['featured_image'] ) && ! $is_existing ) {
                self::import_featured_image( $treatment_id, $treatment_data['featured_image'] );
            }

            // Import packages
            if ( ! empty( $treatment_data['packages'] ) ) {
                // Skip packages if option selected and treatment existed
                if ( $is_existing && $skip_existing_packages ) {
                    $imported_count++;
                    continue;
                }

                // Delete existing packages for this treatment
                PackageRepository::delete_by_treatment( $treatment_id );

                // Import new packages
                foreach ( $treatment_data['packages'] as $index => $package_data ) {
                    $package = new PackageModel();
                    $package->set_treatment_id( $treatment_id );
                    $package->set_name( $package_data['name'] ?? '' );
                    $package->set_sessions( $package_data['sessions'] ?? 1 );
                    $package->set_total_price( $package_data['total_price'] ?? 0 );
                    $package->set_deposit_type( $package_data['deposit_type'] ?? 'none' );
                    $package->set_deposit_value( $package_data['deposit_value'] ?? 0 );
                    $package->set_sort_order( $package_data['sort_order'] ?? $index );

                    PackageRepository::save( $package );
                }
            }

            $imported_count++;
        }

        return $imported_count;
    }

    /**
     * Find treatment by title
     *
     * @param string $title Treatment title.
     * @return \WP_Post|null
     */
    private static function find_treatment_by_title( $title ) {
        $posts = get_posts( array(
            'post_type'      => TreatmentPostType::POST_TYPE,
            'post_status'    => array( 'publish', 'draft', 'private' ),
            'title'          => $title,
            'posts_per_page' => 1,
        ) );

        return ! empty( $posts ) ? $posts[0] : null;
    }

    /**
     * Import featured image from URL
     *
     * @param int    $post_id Post ID.
     * @param string $url     Image URL.
     */
    private static function import_featured_image( $post_id, $url ) {
        if ( empty( $url ) ) {
            return;
        }

        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        // Download and attach the image
        $attachment_id = media_sideload_image( $url, $post_id, null, 'id' );

        if ( ! is_wp_error( $attachment_id ) ) {
            set_post_thumbnail( $post_id, $attachment_id );
        }
    }

    /**
     * Redirect with error message
     *
     * @param string $message Error message.
     */
    private static function redirect_with_error( $message ) {
        wp_redirect( add_query_arg(
            array(
                'post_type'    => TreatmentPostType::POST_TYPE,
                'page'         => 'tp-import-export',
                'import_error' => urlencode( $message ),
            ),
            admin_url( 'edit.php' )
        ) );
        exit;
    }

    /**
     * Count total packages
     *
     * @return int
     */
    private static function count_packages() {
        global $wpdb;
        $table = $wpdb->prefix . 'tp_packages';
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table}" );
    }

    /**
     * Count single session packages
     *
     * @return int
     */
    private static function count_single_session_packages() {
        global $wpdb;
        $table = $wpdb->prefix . 'tp_packages';
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE sessions = 1" );
    }

    /**
     * Count packages with zero price
     *
     * @return int
     */
    private static function count_zero_price_packages() {
        global $wpdb;
        $table = $wpdb->prefix . 'tp_packages';
        return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE total_price = 0 OR total_price IS NULL" );
    }

    /**
     * Handle cleanup request
     */
    public static function handle_cleanup() {
        if ( ! isset( $_POST['tp_cleanup'] ) ) {
            return;
        }

        if ( ! isset( $_POST['tp_cleanup_nonce'] ) || ! wp_verify_nonce( $_POST['tp_cleanup_nonce'], 'tp_cleanup_packages' ) ) {
            return;
        }

        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        $delete_single = isset( $_POST['delete_single_sessions'] );
        $fix_zero = isset( $_POST['fix_zero_prices'] );

        $deleted = 0;
        $updated = 0;

        global $wpdb;
        $table = $wpdb->prefix . 'tp_packages';

        // Fix zero price packages first (before deleting single sessions)
        if ( $fix_zero ) {
            // Get all packages with zero price
            $zero_packages = $wpdb->get_results(
                "SELECT p.*, pm.meta_value as base_price
                FROM {$table} p
                LEFT JOIN {$wpdb->postmeta} pm ON p.treatment_id = pm.post_id AND pm.meta_key = '_tp_base_price'
                WHERE p.total_price = 0 OR p.total_price IS NULL"
            );

            foreach ( $zero_packages as $pkg ) {
                $base_price = floatval( $pkg->base_price );
                if ( $base_price > 0 ) {
                    $new_total = $base_price * intval( $pkg->sessions );
                    $per_session = $base_price;

                    $result = $wpdb->update(
                        $table,
                        array(
                            'total_price'       => $new_total,
                            'per_session_price' => $per_session,
                            'updated_at'        => current_time( 'mysql' ),
                        ),
                        array( 'id' => $pkg->id ),
                        array( '%f', '%f', '%s' ),
                        array( '%d' )
                    );

                    if ( $result !== false ) {
                        $updated++;

                        // Sync WooCommerce product price if exists
                        if ( $pkg->wc_product_id && function_exists( 'wc_get_product' ) ) {
                            $product = wc_get_product( $pkg->wc_product_id );
                            if ( $product ) {
                                $product->set_regular_price( $new_total );
                                $product->set_price( $new_total );
                                $product->save();
                            }
                        }
                    }
                }
            }
        }

        // Delete single session packages
        if ( $delete_single ) {
            // First get the WC product IDs to clean up
            $single_packages = $wpdb->get_results(
                "SELECT id, wc_product_id FROM {$table} WHERE sessions = 1"
            );

            foreach ( $single_packages as $pkg ) {
                // Delete WooCommerce product if exists
                if ( $pkg->wc_product_id && function_exists( 'wc_get_product' ) ) {
                    $product = wc_get_product( $pkg->wc_product_id );
                    if ( $product ) {
                        $product->delete( true );
                    }
                }
            }

            // Delete all single session packages
            $deleted = $wpdb->query( "DELETE FROM {$table} WHERE sessions = 1" );
            if ( $deleted === false ) {
                $deleted = 0;
            }
        }

        // Redirect with success
        wp_redirect( add_query_arg(
            array(
                'post_type'    => TreatmentPostType::POST_TYPE,
                'page'         => 'tp-import-export',
                'cleanup_done' => 1,
                'deleted'      => $deleted,
                'updated'      => $updated,
            ),
            admin_url( 'edit.php' )
        ) );
        exit;
    }
}
