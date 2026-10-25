<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LessonCommentResource extends JsonResource
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
            'lesson_id' => $this->lesson_id,
            'parent_id' => $this->parent_id,
            'body' => $this->body,
            'attachment' => $this->attachment_path ? [
                'name' => $this->attachment_name,
                'mime_type' => $this->attachment_mime,
                'size' => $this->attachment_size,
                'is_image' => in_array($this->attachment_mime, ['image/jpeg', 'image/png', 'image/webp'], true),
                'url' => route('api.v1.me.courses.lessons.comments.attachment', [
                    'course' => $request->route('course'),
                    'lesson' => $request->route('lesson'),
                    'comment' => $this->id,
                ], false),
            ] : null,
            'created_at' => $this->created_at,
            'author' => $this->whenLoaded('user', fn (): array => [
                'id' => $this->user->id,
                'name' => $this->user->name,
            ]),
            'replies_count' => $this->whenCounted('replies'),
            'replies' => self::collection($this->whenLoaded('replies')),
            'next_reply_after' => $this->when($this->relationLoaded('replies'), fn (): ?int => $this->replies_count > $this->replies->count() ? $this->replies->last()?->id : null),
        ];
    }
}
