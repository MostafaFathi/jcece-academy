<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PublicInstructorResource extends JsonResource
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
            'avatar' => $this->avatar,
            'profile' => $this->whenLoaded('instructorProfile', fn (): array => [
                'job_title' => $this->instructorProfile->job_title,
                'short_bio' => $this->instructorProfile->short_bio,
                'bio' => $this->instructorProfile->bio,
                'years_experience' => $this->instructorProfile->years_experience,
                'specialties' => $this->instructorProfile->specialties,
                'linkedin_url' => $this->instructorProfile->linkedin_url,
                'facebook_url' => $this->instructorProfile->facebook_url,
                'instagram_url' => $this->instructorProfile->instagram_url,
                'website_url' => $this->instructorProfile->website_url,
            ]),
            'course_count' => $this->whenCounted('instructedCourses'),
            'courses' => CourseResource::collection($this->whenLoaded('instructedCourses')),
        ];
    }
}
