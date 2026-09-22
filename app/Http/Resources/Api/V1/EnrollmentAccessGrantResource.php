<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EnrollmentAccessGrantResource extends JsonResource
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
            'enrollment_id' => $this->enrollment_id,
            'source_type' => $this->source_type->value,
            'access_starts_at' => $this->access_starts_at,
            'access_expires_at' => $this->access_expires_at,
            'revoked_at' => $this->revoked_at,
            'revoked_by' => $this->revoked_by,
            'revocation_reason' => $this->revocation_reason,
            'created_at' => $this->created_at,
        ];
    }
}
