<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FinancialDocumentResource extends JsonResource
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
            'kind' => $this->kind,
            'document_number' => $this->document_number,
            'order_id' => $this->order_id,
            'refund_id' => $this->refund_id,
            'amount' => $this->amount,
            'currency' => $this->currency,
            'locale' => $this->locale,
            'issued_at' => $this->issued_at,
        ];
    }
}
