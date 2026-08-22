<?php

namespace TreatmentPackages\Packages;

defined('ABSPATH') || exit;

/**
 * Package Model
 *
 * Represents a treatment package entity
 */
class PackageModel {

    private int $id = 0;
    private int $treatment_id = 0;
    private ?int $product_id = null;
    private string $name = '';
    private int $sessions = 1;
    private float $price = 0.00;
    private ?float $sale_price = null;
    private string $deposit_type = 'none';
    private ?float $deposit_amount = null;
    private ?string $description = null;
    private int $sort_order = 0;
    private bool $is_active = true;
    private ?string $created_at = null;
    private ?string $updated_at = null;

    /**
     * Create model from database row
     *
     * @param object|array $data Database row
     * @return self
     */
    public static function from_row($data): self {
        $data = (object) $data;
        $model = new self();

        $model->id = (int) ($data->id ?? 0);
        $model->treatment_id = (int) ($data->treatment_id ?? 0);
        $model->product_id = isset($data->product_id) ? (int) $data->product_id : null;
        $model->name = $data->name ?? '';
        $model->sessions = (int) ($data->sessions ?? 1);
        $model->price = (float) ($data->price ?? 0);
        $model->sale_price = isset($data->sale_price) ? (float) $data->sale_price : null;
        $model->deposit_type = $data->deposit_type ?? 'none';
        $model->deposit_amount = isset($data->deposit_amount) ? (float) $data->deposit_amount : null;
        $model->description = $data->description ?? null;
        $model->sort_order = (int) ($data->sort_order ?? 0);
        $model->is_active = (bool) ($data->is_active ?? true);
        $model->created_at = $data->created_at ?? null;
        $model->updated_at = $data->updated_at ?? null;

        return $model;
    }

    /**
     * Convert to array for database insertion
     *
     * @return array
     */
    public function to_array(): array {
        return [
            'treatment_id' => $this->treatment_id,
            'product_id' => $this->product_id,
            'name' => $this->name,
            'sessions' => $this->sessions,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'deposit_type' => $this->deposit_type,
            'deposit_amount' => $this->deposit_amount,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_active' => $this->is_active ? 1 : 0,
        ];
    }

    // Getters
    public function get_id(): int {
        return $this->id;
    }

    public function get_treatment_id(): int {
        return $this->treatment_id;
    }

    public function get_product_id(): ?int {
        return $this->product_id;
    }

    public function get_name(): string {
        return $this->name;
    }

    public function get_sessions(): int {
        return $this->sessions;
    }

    public function get_price(): float {
        return $this->price;
    }

    public function get_sale_price(): ?float {
        return $this->sale_price;
    }

    public function get_active_price(): float {
        return $this->sale_price !== null ? $this->sale_price : $this->price;
    }

    public function get_deposit_type(): string {
        return $this->deposit_type;
    }

    public function get_deposit_amount(): ?float {
        return $this->deposit_amount;
    }

    public function get_description(): ?string {
        return $this->description;
    }

    public function get_sort_order(): int {
        return $this->sort_order;
    }

    public function is_active(): bool {
        return $this->is_active;
    }

    public function get_created_at(): ?string {
        return $this->created_at;
    }

    public function get_updated_at(): ?string {
        return $this->updated_at;
    }

    // Setters
    public function set_id(int $id): self {
        $this->id = $id;
        return $this;
    }

    public function set_treatment_id(int $treatment_id): self {
        $this->treatment_id = $treatment_id;
        return $this;
    }

    public function set_product_id(?int $product_id): self {
        $this->product_id = $product_id;
        return $this;
    }

    public function set_name(string $name): self {
        $this->name = $name;
        return $this;
    }

    public function set_sessions(int $sessions): self {
        $this->sessions = max(1, $sessions);
        return $this;
    }

    public function set_price(float $price): self {
        $this->price = max(0, $price);
        return $this;
    }

    public function set_sale_price(?float $sale_price): self {
        $this->sale_price = $sale_price !== null ? max(0, $sale_price) : null;
        return $this;
    }

    public function set_deposit_type(string $deposit_type): self {
        $valid_types = ['none', 'fixed', 'percentage', 'pay_at_location'];
        $this->deposit_type = in_array($deposit_type, $valid_types) ? $deposit_type : 'none';
        return $this;
    }

    public function set_deposit_amount(?float $deposit_amount): self {
        $this->deposit_amount = $deposit_amount !== null ? max(0, $deposit_amount) : null;
        return $this;
    }

    public function set_description(?string $description): self {
        $this->description = $description;
        return $this;
    }

    public function set_sort_order(int $sort_order): self {
        $this->sort_order = $sort_order;
        return $this;
    }

    public function set_is_active(bool $is_active): self {
        $this->is_active = $is_active;
        return $this;
    }

    /**
     * Calculate deposit amount for checkout
     *
     * @return float
     */
    public function calculate_deposit(): float {
        $price = $this->get_active_price();

        switch ($this->deposit_type) {
            case 'fixed':
                return min($this->deposit_amount ?? 0, $price);

            case 'percentage':
                $percentage = min($this->deposit_amount ?? 0, 100);
                return round($price * ($percentage / 100), 2);

            case 'pay_at_location':
                return 0;

            case 'none':
            default:
                return $price;
        }
    }

    /**
     * Calculate balance due after deposit
     *
     * @return float
     */
    public function calculate_balance(): float {
        return $this->get_active_price() - $this->calculate_deposit();
    }

    /**
     * Get price per session
     *
     * @return float
     */
    public function get_price_per_session(): float {
        if ($this->sessions === 0) {
            return 0;
        }
        return round($this->get_active_price() / $this->sessions, 2);
    }

    /**
     * Get savings compared to single session price
     *
     * @param float $single_session_price Base price for single session
     * @return float
     */
    public function get_savings(float $single_session_price): float {
        $full_price = $single_session_price * $this->sessions;
        return max(0, $full_price - $this->get_active_price());
    }

    /**
     * Get savings percentage
     *
     * @param float $single_session_price Base price for single session
     * @return float
     */
    public function get_savings_percentage(float $single_session_price): float {
        $full_price = $single_session_price * $this->sessions;
        if ($full_price === 0.0) {
            return 0;
        }
        return round(($this->get_savings($single_session_price) / $full_price) * 100, 1);
    }
}
