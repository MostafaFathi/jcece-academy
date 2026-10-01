<?php

namespace App\Http\Resources\Api\V1;

use App\PermissionName;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminInstructorResource extends JsonResource
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
            'name' => $this->name,
            'email' => $this->when($request->user()?->can(PermissionName::InstructorsManage->value), $this->email),
            'status' => $this->status->value,
            'profile' => $this->whenLoaded('instructorProfile', fn (): ?array => $this->instructorProfile === null ? null : [
                'job_title' => $this->instructorProfile->job_title,
                'short_bio' => $this->instructorProfile->short_bio,
                'bio' => $this->instructorProfile->bio,
                'years_experience' => $this->instructorProfile->years_experience,
                'specialties' => $this->instructorProfile->specialties,
                'linkedin_url' => $this->instructorProfile->linkedin_url,
                'facebook_url' => $this->instructorProfile->facebook_url,
                'instagram_url' => $this->instructorProfile->instagram_url,
                'website_url' => $this->instructorProfile->website_url,
                'is_featured' => $this->instructorProfile->is_featured,
            ]),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
