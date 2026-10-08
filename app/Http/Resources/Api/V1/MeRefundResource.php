<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeRefundResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'amount' => $this->amount, 'currency' => $this->currency, 'reason' => $this->reason, 'status' => $this->status, 'access_effect' => $this->access_effect, 'created_at' => $this->created_at, 'processed_at' => $this->processed_at];
    }
}
