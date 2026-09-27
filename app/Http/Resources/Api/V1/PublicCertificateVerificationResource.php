<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicCertificateVerificationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'status' => $this->status->value,
            'certificate_number' => $this->certificate_number,
            'student_name' => $this->student_name_snapshot,
            'course_title' => $this->course_title_snapshot,
            'issued_at' => $this->issued_at,
        ];
    }
}
