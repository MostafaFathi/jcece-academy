<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['coupon_id', 'order_id', 'user_id', 'status', 'consumed_at'])]
class CouponRedemption extends Model
{
    protected function casts(): array
    {
        return ['consumed_at' => 'datetime'];
    }
}
