<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminPaymentResource extends JsonResource
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
            'order_id' => $this->order_id,
            'order_number' => $this->whenLoaded('order', fn (): string => $this->order->order_number),
            'transaction_id' => $this->transaction_id,
            'method' => $this->method->value,
            'gateway' => $this->gateway,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'proof_available' => $this->payment_proof !== null,
            'paid_at' => $this->paid_at,
            'approver' => new UserSummaryResource($this->whenLoaded('approver')),
            'approved_at' => $this->approved_at,
            'rejection_reason' => $this->rejection_reason,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
