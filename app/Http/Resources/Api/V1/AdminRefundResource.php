<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminRefundResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'amount' => $this->amount, 'currency' => $this->currency, 'reason' => $this->reason, 'internal_note' => $this->internal_note, 'status' => $this->status, 'access_effect' => $this->access_effect, 'order_item_ids' => $this->order_item_ids, 'external_reference' => $this->external_reference, 'initiated_by' => $this->initiated_by, 'processed_by' => $this->processed_by, 'created_at' => $this->created_at, 'processed_at' => $this->processed_at];
    }
}
