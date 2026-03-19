<?php
/**
 * Package Model
 *
 * @package TreatmentPackages\Packages
 */

namespace TreatmentPackages\Packages;

defined( 'ABSPATH' ) || exit;

/**
 * PackageModel Class
 *
 * Represents a treatment package with sessions, pricing, and deposit configuration.
 */
class PackageModel {

    /**
     * Package ID
     *
     * @var int
     */
    protected $id = 0;

    /**
     * Treatment ID (post ID)
     *
     * @var int
     */
    protected $treatment_id = 0;

    /**
     * Package name
     *
     * @var string
     */
    protected $name = '';

    /**
     * Number of sessions
     *
     * @var int
     */
    protected $sessions = 1;

    /**
     * Total package price
     *
     * @var float
     */
    protected $total_price = 0.00;

    /**
     * Price per session (computed)
     *
     * @var float
     */
    protected $per_session_price = 0.00;

    /**
     * Discount percentage (computed)
     *
     * @var float
     */
    protected $discount_percent = 0.00;

    /**
     * Deposit type: none, fixed, percentage
     *
     * @var string
     */
    protected $deposit_type = 'none';

    /**
     * Deposit value (amount or percentage)
     *
     * @var float
     */
    protected $deposit_value = 0.00;

    /**
     * WooCommerce product ID
     *
     * @var int|null
     */
    protected $wc_product_id = null;

    /**
     * Sort order
     *
     * @var int
     */
    protected $sort_order = 0;

    /**
     * Created timestamp
     *
     * @var string
     */
    protected $created_at = '';

    /**
     * Updated timestamp
     *
     * @var string
     */
    protected $updated_at = '';

    /**
     * Constructor
     *
     * @param array|object $data Optional. Package data to populate the model.
     */
    public function __construct( $data = null ) {
        if ( $data ) {
            $this->populate( $data );
        }
    }

    /**
     * Populate model from data array or object
     *
     * @param array|object $data Package data.
     * @return self
     */
    public function populate( $data ) {
        $data = (array) $data;

        $properties = array(
            'id',
            'treatment_id',
            'name',
            'sessions',
            'total_price',
            'per_session_price',
            'discount_percent',
            'deposit_type',
            'deposit_value',
            'wc_product_id',
            'sort_order',
            'created_at',
            'updated_at',
        );

        foreach ( $properties as $prop ) {
            if ( isset( $data[ $prop ] ) ) {
                $this->$prop = $data[ $prop ];
            }
        }

        // Type casting
        $this->id                = (int) $this->id;
        $this->treatment_id      = (int) $this->treatment_id;
        $this->sessions          = (int) $this->sessions;
        $this->total_price       = (float) $this->total_price;
        $this->per_session_price = (float) $this->per_session_price;
        $this->discount_percent  = (float) $this->discount_percent;
        $this->deposit_value     = (float) $this->deposit_value;
        $this->wc_product_id     = $this->wc_product_id ? (int) $this->wc_product_id : null;
        $this->sort_order        = (int) $this->sort_order;

        return $this;
    }

    /**
     * Convert model to array
     *
     * @return array
     */
    public function to_array() {
        return array(
            'id'                => $this->id,
            'treatment_id'      => $this->treatment_id,
            'name'              => $this->name,
            'sessions'          => $this->sessions,
            'total_price'       => $this->total_price,
            'per_session_price' => $this->per_session_price,
            'discount_percent'  => $this->discount_percent,
            'deposit_type'      => $this->deposit_type,
            'deposit_value'     => $this->deposit_value,
            'wc_product_id'     => $this->wc_product_id,
            'sort_order'        => $this->sort_order,
            'created_at'        => $this->created_at,
            'updated_at'        => $this->updated_at,
        );
    }

    /**
     * Calculate per-session price and discount percent
     *
     * @param float|null $base_price Optional. Base price for single session (for discount calculation).
     * @return self
     */
    public function calculate_prices( $base_price = null ) {
        // Calculate per-session price
        if ( $this->sessions > 0 ) {
            $this->per_session_price = round( $this->total_price / $this->sessions, 2 );
        }

        // Calculate discount percentage if base price is provided
        if ( $base_price && $base_price > 0 && $this->per_session_price > 0 ) {
            $this->discount_percent = round(
                ( ( $base_price - $this->per_session_price ) / $base_price ) * 100,
                2
            );
        }

        return $this;
    }

    /**
     * Calculate the deposit amount for this package
     *
     * @return float
     */
    public function calculate_deposit() {
        switch ( $this->deposit_type ) {
            case 'fixed':
                return min( $this->deposit_value, $this->total_price );

            case 'percentage':
                return round( ( $this->total_price * $this->deposit_value ) / 100, 2 );

            case 'none':
            default:
                return $this->total_price;
        }
    }

    /**
     * Calculate the remaining balance after deposit
     *
     * @return float
     */
    public function calculate_remaining_balance() {
        $deposit = $this->calculate_deposit();
        return max( 0, $this->total_price - $deposit );
    }

    /**
     * Check if package requires a deposit
     *
     * @return bool
     */
    public function has_deposit() {
        return 'none' !== $this->deposit_type && $this->deposit_value > 0;
    }

    /**
     * Get the treatment post object
     *
     * @return \WP_Post|null
     */
    public function get_treatment() {
        if ( ! $this->treatment_id ) {
            return null;
        }
        return get_post( $this->treatment_id );
    }

    /**
     * Get the treatment title
     *
     * @return string
     */
    public function get_treatment_title() {
        $treatment = $this->get_treatment();
        return $treatment ? $treatment->post_title : '';
    }

    /**
     * Get the WooCommerce product object
     *
     * @return \WC_Product|null
     */
    public function get_wc_product() {
        if ( ! $this->wc_product_id || ! function_exists( 'wc_get_product' ) ) {
            return null;
        }
        return wc_get_product( $this->wc_product_id );
    }

    /**
     * Get formatted total price
     *
     * @return string
     */
    public function get_formatted_total_price() {
        if ( function_exists( 'wc_price' ) ) {
            return wc_price( $this->total_price );
        }
        return get_woocommerce_currency_symbol() . number_format( $this->total_price, 2 );
    }

    /**
     * Get formatted per-session price
     *
     * @return string
     */
    public function get_formatted_per_session_price() {
        if ( function_exists( 'wc_price' ) ) {
            return wc_price( $this->per_session_price );
        }
        return get_woocommerce_currency_symbol() . number_format( $this->per_session_price, 2 );
    }

    /**
     * Get formatted deposit amount
     *
     * @return string
     */
    public function get_formatted_deposit() {
        $deposit = $this->calculate_deposit();
        if ( function_exists( 'wc_price' ) ) {
            return wc_price( $deposit );
        }
        return get_woocommerce_currency_symbol() . number_format( $deposit, 2 );
    }

    /**
     * Get display name for the package
     *
     * @return string
     */
    public function get_display_name() {
        if ( ! empty( $this->name ) ) {
            return $this->name;
        }

        if ( 1 === $this->sessions ) {
            return __( 'Pay as you go', 'treatpack' );
        }

        return sprintf(
            /* translators: %d: number of sessions */
            __( 'Course of %d', 'treatpack' ),
            $this->sessions
        );
    }

    /**
     * Get discount badge text
     *
     * @return string
     */
    public function get_discount_badge() {
        if ( $this->discount_percent > 0 ) {
            return sprintf(
                /* translators: %s: discount percentage */
                __( '%s%% off', 'treatpack' ),
                number_format( $this->discount_percent, 0 )
            );
        }
        return '';
    }

    // =========================================================================
    // Getters
    // =========================================================================

    /**
     * Get package ID
     *
     * @return int
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get treatment ID
     *
     * @return int
     */
    public function get_treatment_id() {
        return $this->treatment_id;
    }

    /**
     * Get package name
     *
     * @return string
     */
    public function get_name() {
        return $this->name;
    }

    /**
     * Get number of sessions
     *
     * @return int
     */
    public function get_sessions() {
        return $this->sessions;
    }

    /**
     * Get total price
     *
     * @return float
     */
    public function get_total_price() {
        return $this->total_price;
    }

    /**
     * Get per-session price
     *
     * @return float
     */
    public function get_per_session_price() {
        return $this->per_session_price;
    }

    /**
     * Get discount percent
     *
     * @return float
     */
    public function get_discount_percent() {
        return $this->discount_percent;
    }

    /**
     * Get deposit type
     *
     * @return string
     */
    public function get_deposit_type() {
        return $this->deposit_type;
    }

    /**
     * Get deposit value
     *
     * @return float
     */
    public function get_deposit_value() {
        return $this->deposit_value;
    }

    /**
     * Get WooCommerce product ID
     *
     * @return int|null
     */
    public function get_wc_product_id() {
        return $this->wc_product_id;
    }

    /**
     * Get sort order
     *
     * @return int
     */
    public function get_sort_order() {
        return $this->sort_order;
    }

    // =========================================================================
    // Setters
    // =========================================================================

    /**
     * Set package ID
     *
     * @param int $id Package ID.
     * @return self
     */
    public function set_id( $id ) {
        $this->id = (int) $id;
        return $this;
    }

    /**
     * Set treatment ID
     *
     * @param int $treatment_id Treatment post ID.
     * @return self
     */
    public function set_treatment_id( $treatment_id ) {
        $this->treatment_id = (int) $treatment_id;
        return $this;
    }

    /**
     * Set package name
     *
     * @param string $name Package name.
     * @return self
     */
    public function set_name( $name ) {
        $this->name = sanitize_text_field( $name );
        return $this;
    }

    /**
     * Set number of sessions
     *
     * @param int $sessions Number of sessions.
     * @return self
     */
    public function set_sessions( $sessions ) {
        $this->sessions = max( 1, (int) $sessions );
        return $this;
    }

    /**
     * Set total price
     *
     * @param float $price Total price.
     * @return self
     */
    public function set_total_price( $price ) {
        $this->total_price = max( 0, (float) $price );
        return $this;
    }

    /**
     * Set deposit type
     *
     * @param string $type Deposit type: none, fixed, percentage.
     * @return self
     */
    public function set_deposit_type( $type ) {
        $valid_types = array( 'none', 'fixed', 'percentage' );
        $this->deposit_type = in_array( $type, $valid_types, true ) ? $type : 'none';
        return $this;
    }

    /**
     * Set deposit value
     *
     * @param float $value Deposit amount or percentage.
     * @return self
     */
    public function set_deposit_value( $value ) {
        $this->deposit_value = max( 0, (float) $value );
        return $this;
    }

    /**
     * Set WooCommerce product ID
     *
     * @param int|null $product_id WooCommerce product ID.
     * @return self
     */
    public function set_wc_product_id( $product_id ) {
        $this->wc_product_id = $product_id ? (int) $product_id : null;
        return $this;
    }

    /**
     * Set sort order
     *
     * @param int $order Sort order.
     * @return self
     */
    public function set_sort_order( $order ) {
        $this->sort_order = (int) $order;
        return $this;
    }
}
