<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['order_id', 'amount', 'currency', 'reason', 'internal_note', 'status', 'access_effect', 'order_item_ids', 'external_reference', 'initiated_by', 'processed_by', 'processed_at'])]
class Refund extends Model
{
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function financialDocument(): HasOne
    {
        return $this->hasOne(FinancialDocument::class);
    }

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'order_item_ids' => 'array', 'processed_at' => 'datetime'];
    }
}
