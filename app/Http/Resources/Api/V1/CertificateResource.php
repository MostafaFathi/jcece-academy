<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CertificateResource extends JsonResource
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
            'course_id' => $this->course_id,
            'certificate_number' => $this->certificate_number,
            'student_name' => $this->student_name_snapshot,
            'course_title' => $this->course_title_snapshot,
            'instructor_name' => $this->instructor_name_snapshot,
            'status' => $this->status->value,
            'completed_at' => $this->completed_at,
            'issued_at' => $this->issued_at,
            'revoked_at' => $this->revoked_at,
            'revocation_reason' => $this->revocation_reason,
            'revoked_by' => $this->whenLoaded('revokedBy', fn (): ?array => $this->revokedBy === null ? null : [
                'id' => $this->revokedBy->id,
                'name' => $this->revokedBy->name,
            ]),
            'verification_url' => route('certificates.verify.page', ['token' => $this->verification_token]),
            'download_url' => $this->when($request->user() !== null, fn (): string => $request->user()->id === $this->user_id
                ? route('api.v1.me.certificates.download', $this->resource)
                : route('api.v1.admin.certificates.download', $this->resource)),
        ];
    }
}
