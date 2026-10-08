<?php

namespace App\Models;

use Database\Factories\FinancialDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

#[Fillable(['order_id', 'refund_id', 'source_key', 'document_number', 'kind', 'locale', 'customer_name', 'customer_email', 'currency', 'amount', 'snapshot', 'pdf_disk', 'pdf_path', 'pdf_sha256', 'issued_at'])]
class FinancialDocument extends Model
{
    /** @use HasFactory<FinancialDocumentFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updating(fn (): never => throw new LogicException('Issued financial documents are append-only.'));
        static::deleting(fn (): never => throw new LogicException('Issued financial documents are append-only.'));
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function refund(): BelongsTo
    {
        return $this->belongsTo(Refund::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'snapshot' => 'array', 'issued_at' => 'datetime'];
    }
}
