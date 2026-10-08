<?php

namespace App\Models;

use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'is_active', 'discount_type', 'discount_value', 'starts_at', 'expires_at', 'usage_limit', 'per_user_limit', 'minimum_order_amount', 'applies_to', 'product_ids', 'created_by', 'updated_by'])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'discount_value' => 'decimal:2', 'minimum_order_amount' => 'decimal:2', 'product_ids' => 'array', 'starts_at' => 'datetime', 'expires_at' => 'datetime'];
    }
}
