<?php

namespace App\Http\Resources\Api\V1;

use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'payment_proof_max_kilobytes' => (int) config('jcec.commerce.payment_proof_max_kilobytes'),
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'total' => $this->total,
            'coupon_code' => $this->coupon_code_snapshot,
            'coupon_type' => $this->coupon_type_snapshot,
            'coupon_value' => $this->coupon_value_snapshot,
            'refund_balance' => app(RefundService::class)->balance($this->resource),
            'refunds' => MeRefundResource::collection($this->whenLoaded('refunds')),
            'financial_documents' => FinancialDocumentResource::collection($this->whenLoaded('financialDocuments')),
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'notes' => $this->notes,
            'placed_at' => $this->placed_at,
            'paid_at' => $this->paid_at,
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payments' => MePaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at,
        ];
    }
}
