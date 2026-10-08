<?php

namespace App\Models;

use Database\Factories\OrderItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Financial snapshot. Catalog updates must never rewrite these attributes.
 */
#[Fillable(['order_id', 'purchasable_type', 'purchasable_id', 'title', 'quantity', 'unit_price', 'discount_amount', 'promotional_discount_amount', 'coupon_discount_amount', 'total', 'access_duration_days', 'sequential_completion_percentage'])]
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function purchasable(): MorphTo
    {
        return $this->morphTo();
    }

    public function packageCourses(): HasMany
    {
        return $this->hasMany(OrderItemPackageCourse::class)->orderBy('sort_order')->orderBy('id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'unit_price' => 'decimal:2',
            'discount_amount' => 'decimal:2',
            'promotional_discount_amount' => 'decimal:2',
            'coupon_discount_amount' => 'decimal:2',
            'total' => 'decimal:2',
            'sequential_completion_percentage' => 'decimal:2',
        ];
    }
}
