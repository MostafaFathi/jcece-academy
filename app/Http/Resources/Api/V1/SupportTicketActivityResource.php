<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupportTicketActivityResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'event_type' => $this->event_type->value, 'field' => $this->field_name, 'previous_value' => $this->previous_value, 'new_value' => $this->new_value, 'actor_name' => $this->actor_name_snapshot, 'created_at' => $this->created_at];
    }
}
