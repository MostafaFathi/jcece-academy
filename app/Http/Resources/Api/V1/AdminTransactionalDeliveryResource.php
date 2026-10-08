<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminTransactionalDeliveryResource extends JsonResource
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
            'message_type' => $this->message_type,
            'recipient_email' => $this->recipient_email,
            'locale' => $this->locale,
            'status' => $this->status,
            'attempts' => $this->attempts,
            'error_code' => $this->error_code,
            'queued_at' => $this->queued_at,
            'sent_at' => $this->sent_at,
            'failed_at' => $this->failed_at,
        ];
    }
}
