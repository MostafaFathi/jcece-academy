<?php

namespace App\Http\Resources\Api\V1;

use App\Services\RefundService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminOrderResource extends JsonResource
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
            'subtotal' => $this->subtotal,
            'discount_total' => $this->discount_total,
            'tax_total' => $this->tax_total,
            'total' => $this->total,
            'coupon_code' => $this->coupon_code_snapshot,
            'coupon_type' => $this->coupon_type_snapshot,
            'coupon_value' => $this->coupon_value_snapshot,
            'refund_balance' => app(RefundService::class)->balance($this->resource),
            'refunds' => $request->user()?->can('refunds.manage')
                ? AdminRefundResource::collection($this->whenLoaded('refunds'))
                : MeRefundResource::collection($this->whenLoaded('refunds')),
            'financial_documents' => FinancialDocumentResource::collection($this->whenLoaded('financialDocuments')),
            'transactional_deliveries' => AdminTransactionalDeliveryResource::collection($this->whenLoaded('transactionalDeliveries')),
            'customer_name' => $this->customer_name,
            'customer_email' => $this->customer_email,
            'customer_phone' => $this->customer_phone,
            'notes' => $this->notes,
            'placed_at' => $this->placed_at,
            'paid_at' => $this->paid_at,
            'user' => new UserSummaryResource($this->whenLoaded('user')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'payments' => AdminPaymentResource::collection($this->whenLoaded('payments')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
