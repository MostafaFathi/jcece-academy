<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AuthenticatedUserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'avatar' => $this->avatar,
            'country' => $this->country,
            'city' => $this->city,
            'specialization' => $this->specialization,
            'status' => $this->status->value,
            'roles' => $this->getRoleNames()->values(),
            'permissions' => $this->getAllPermissions()->pluck('name')->sort()->values(),
            'instructor_profile' => $this->whenLoaded('instructorProfile', fn (): ?array => $this->instructorProfile === null ? null : [
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
        ];
    }
}
