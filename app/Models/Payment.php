<?php

namespace App\Models;

use App\PaymentMethod;
use App\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['order_id', 'transaction_id', 'method', 'gateway', 'amount', 'currency', 'status', 'payment_proof', 'gateway_response', 'paid_at', 'approved_by', 'approved_at', 'rejection_reason'])]
#[Hidden(['payment_proof', 'gateway_response'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'amount' => 'decimal:2',
            'status' => PaymentStatus::class,
            'gateway_response' => 'array',
            'paid_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }
}
