<?php

namespace App\Models;

use Database\Factories\OrderItemPackageCourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable package composition captured at order creation.
 */
#[Fillable(['order_item_id', 'course_id', 'course_title', 'sort_order'])]
class OrderItemPackageCourse extends Model
{
    /** @use HasFactory<OrderItemPackageCourseFactory> */
    use HasFactory;

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }
}
