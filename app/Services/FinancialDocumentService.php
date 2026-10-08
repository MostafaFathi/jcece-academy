<?php

namespace App\Services;

use App\Models\FinancialDocument;
use App\Models\Order;
use App\Models\Refund;
use App\OrderStatus;
use App\PaymentStatus;
use Brick\Math\BigDecimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class FinancialDocumentService
{
    public function __construct(private MpdfFinancialDocumentGenerator $generator, private AuditTrail $audit, private RefundService $refunds) {}

    public function schedulePurchase(Order $order): void
    {
        DB::afterCommit(function () use ($order): void {
            try {
                $this->issuePurchase($order);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    public function scheduleRefund(Refund $refund): void
    {
        DB::afterCommit(function () use ($refund): void {
            try {
                $this->issueRefund($refund);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }

    public function issuePurchase(Order $order): FinancialDocument
    {
        return $this->issue($order, null);
    }

    public function issueRefund(Refund $refund): FinancialDocument
    {
        $currentRefund = Refund::query()->findOrFail($refund->id);
        if ($currentRefund->status !== 'completed' || $currentRefund->processed_at === null) {
            throw ValidationException::withMessages(['refund' => 'A refund receipt requires a completed refund.']);
        }

        $this->issuePurchase($refund->order);

        return $this->issue($refund->order, $refund);
    }

    private function issue(Order $order, ?Refund $refund): FinancialDocument
    {
        $storedFile = null;

        try {
            return DB::transaction(function () use ($order, $refund, &$storedFile): FinancialDocument {
                $lockedOrder = Order::query()->lockForUpdate()->findOrFail($order->id);
                $lockedRefund = $refund === null ? null : Refund::query()->whereBelongsTo($lockedOrder)->lockForUpdate()->findOrFail($refund->id);
                $sourceKey = $lockedRefund === null ? "order:{$lockedOrder->id}" : "refund:{$lockedRefund->id}";
                $existing = FinancialDocument::query()->where('source_key', $sourceKey)->first();

                if ($existing !== null) {
                    return $existing;
                }

                if (! in_array($lockedOrder->status, [OrderStatus::Completed, OrderStatus::Refunded], true) || $lockedOrder->paid_at === null) {
                    throw ValidationException::withMessages(['order' => 'A purchase receipt requires a completed order.']);
                }
                if ($lockedRefund !== null && ($lockedRefund->status !== 'completed' || $lockedRefund->processed_at === null)) {
                    throw ValidationException::withMessages(['refund' => 'A refund receipt requires a completed refund.']);
                }

                $issuedAt = now();
                $kind = $lockedRefund === null ? 'purchase' : 'refund';
                $number = 'JCEC-'.($kind === 'purchase' ? 'OR' : 'RR').'-'.$issuedAt->format('Y').'-'.Str::upper((string) Str::ulid());
                $snapshot = $lockedRefund === null ? $this->purchaseSnapshot($lockedOrder) : $this->refundSnapshot($lockedOrder, $lockedRefund);
                $locale = in_array($lockedOrder->locale_snapshot, ['ar', 'en'], true) ? $lockedOrder->locale_snapshot : 'ar';
                $diskName = (string) config('jcec.financial_documents.pdf_disk', 'local');
                if ($diskName === 'public' || config("filesystems.disks.{$diskName}.visibility") === 'public') {
                    throw new RuntimeException('Financial documents require private storage.');
                }

                $document = new FinancialDocument([
                    'order_id' => $lockedOrder->id,
                    'refund_id' => $lockedRefund?->id,
                    'source_key' => $sourceKey,
                    'document_number' => $number,
                    'kind' => $kind,
                    'locale' => $locale,
                    'customer_name' => $lockedOrder->customer_name,
                    'customer_email' => $lockedOrder->customer_email,
                    'currency' => $lockedOrder->currency,
                    'amount' => $lockedRefund?->amount ?? $lockedOrder->total,
                    'snapshot' => $snapshot,
                    'issued_at' => $issuedAt,
                ]);
                $bytes = $this->generator->generate($document);
                $path = "financial-documents/{$number}.pdf";
                if (! Storage::disk($diskName)->put($path, $bytes)) {
                    throw new RuntimeException('The financial document PDF could not be stored.');
                }
                $storedFile = ['disk' => $diskName, 'path' => $path];
                $document->pdf_disk = $diskName;
                $document->pdf_path = $path;
                $document->pdf_sha256 = hash('sha256', $bytes);
                $document->save();
                $this->audit->record($kind === 'purchase' ? 'financial_document.purchase_issued' : 'financial_document.refund_issued', $document, null, ['order_id' => $lockedOrder->id, 'refund_id' => $lockedRefund?->id]);

                return $document->refresh();
            });
        } catch (Throwable $exception) {
            if ($storedFile !== null) {
                Storage::disk($storedFile['disk'])->delete($storedFile['path']);
            }

            throw $exception;
        }
    }

    /** @return array<string, mixed> */
    private function purchaseSnapshot(Order $order): array
    {
        $payment = $order->payments()->where('status', PaymentStatus::Paid)->oldest('id')->first();

        return [
            'order_number' => $order->order_number,
            'paid_at' => $order->paid_at?->toIso8601String(),
            'payment_method' => $payment?->method?->value ?? (BigDecimal::of($order->total)->isZero() ? 'complimentary' : 'recorded_payment'),
            'coupon_code' => $order->coupon_code_snapshot,
            'coupon_type' => $order->coupon_type_snapshot,
            'coupon_value' => $order->coupon_value_snapshot,
            'subtotal' => $order->subtotal,
            'discount_total' => $order->discount_total,
            'tax_total' => $order->tax_total,
            'total' => $order->total,
            'items' => $order->items()->get()->map(fn ($item): array => [
                'title' => $item->title,
                'type' => $item->purchasable_type,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'promotional_discount' => $item->promotional_discount_amount,
                'coupon_discount' => $item->coupon_discount_amount,
                'discount' => $item->discount_amount,
                'total' => $item->total,
            ])->all(),
        ];
    }

    /** @return array<string, mixed> */
    private function refundSnapshot(Order $order, Refund $refund): array
    {
        $purchase = FinancialDocument::query()->where('source_key', "order:{$order->id}")->first();
        $items = $refund->access_effect === 'items'
            ? $order->items()->whereIn('id', $refund->order_item_ids ?? [])->get()->map(fn ($item): array => ['title' => $item->title, 'amount' => $item->total])->all()
            : [];
        $balance = $this->refunds->balance($order->load('payments', 'refunds'));

        return [
            'order_number' => $order->order_number,
            'purchase_document_number' => $purchase?->document_number,
            'refund_reference' => $refund->id,
            'completed_at' => $refund->processed_at->toIso8601String(),
            'reason' => $refund->reason,
            'access_effect' => $refund->access_effect,
            'selected_items' => $items,
            'refund_amount' => $refund->amount,
            'original_total' => $order->total,
            'completed_refunds' => $balance['refunded'],
            'remaining_net' => (string) BigDecimal::of($balance['paid'])->minus($balance['refunded']),
        ];
    }
}
