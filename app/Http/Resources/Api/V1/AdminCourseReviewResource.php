<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminCourseReviewResource extends JsonResource
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
            'course' => $this->whenLoaded('course', fn (): array => [
                'id' => $this->course->id,
                'title' => $this->course->title,
                'slug' => $this->course->slug,
            ]),
            'reviewer' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'rating' => $this->rating,
            'title' => $this->title,
            'body' => $this->body,
            'status' => $this->status->value,
            'submitted_at' => $this->submitted_at,
            'published_at' => $this->published_at,
            'moderator' => $this->whenLoaded('moderator', fn (): ?array => $this->moderator === null ? null : [
                'id' => $this->moderator->id,
                'name' => $this->moderator->name,
            ]),
            'moderated_at' => $this->moderated_at,
            'moderation_reason' => $this->moderation_reason,
            'history' => $this->whenLoaded('histories', fn () => $this->histories->map(fn ($history): array => [
                'id' => $history->id,
                'action' => $history->action,
                'actor_name' => $history->actor_name_snapshot,
                'from_status' => $history->from_status?->value,
                'to_status' => $history->to_status->value,
                'old_rating' => $history->old_rating,
                'new_rating' => $history->new_rating,
                'old_title' => $history->old_title,
                'new_title' => $history->new_title,
                'old_body' => $history->old_body,
                'new_body' => $history->new_body,
                'reason' => $history->reason,
                'created_at' => $history->created_at,
            ])),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
