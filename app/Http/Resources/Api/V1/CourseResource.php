<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Assignment;
use App\Models\Quiz;
use App\Services\CommerceCatalogService;
use App\Services\CommercePricingService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;

class CourseResource extends JsonResource
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
            'title' => $this->title,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'thumbnail' => $this->thumbnail,
            'promo_video_url' => $this->promo_video_url,
            'level' => $this->level->value,
            'training_type' => $this->training_type?->value ?? 'recorded',
            'language' => $this->language,
            'duration_minutes' => $this->duration_minutes,
            'access_duration_days' => $this->access_duration_days,
            'price' => app(CommercePricingService::class)->product($this->resource)['active_price'],
            'pricing' => app(CommercePricingService::class)->product($this->resource),
            'currency' => app(CommerceCatalogService::class)->currency(),
            'compare_price' => $request->routeIs('api.v1.admin.*') ? $this->compare_price : (app(CommercePricingService::class)->product($this->resource)['promotion_active'] ? $this->price : null),
            'legacy_compare_price' => $this->when($request->routeIs('api.v1.admin.*'), $this->compare_price),
            'promotional_price' => $this->promotional_price,
            'discount_starts_at' => $this->discount_starts_at,
            'discount_ends_at' => $this->discount_ends_at,
            'certificate_enabled' => $this->certificate_enabled,
            'discussion_enabled' => $this->discussion_enabled,
            'status' => $this->status->value,
            'capabilities' => $this->when(
                $request->user() !== null && $request->routeIs('api.v1.admin.*', 'api.v1.instructor.*'),
                fn (): array => [
                    'can_update_course' => Gate::forUser($request->user())->allows('update', $this->resource),
                    'can_delete_course' => Gate::forUser($request->user())->allows('delete', $this->resource),
                    'can_view_curriculum' => Gate::forUser($request->user())->allows('viewCurriculum', $this->resource),
                    'can_create_curriculum' => Gate::forUser($request->user())->allows('createCurriculum', $this->resource),
                    'can_update_curriculum' => Gate::forUser($request->user())->allows('updateCurriculum', $this->resource),
                    'can_delete_curriculum' => Gate::forUser($request->user())->allows('deleteCurriculum', $this->resource),
                    'can_create_quiz' => Gate::forUser($request->user())->allows('create', [Quiz::class, $this->resource]),
                    'can_create_assignment' => Gate::forUser($request->user())->allows('create', [Assignment::class, $this->resource]),
                ],
            ),
            'is_featured' => $this->is_featured,
            'published_at' => $this->published_at,
            'rating_summary' => $this->when(
                array_key_exists('published_reviews_count', $this->resource->getAttributes()),
                fn (): CourseRatingSummaryResource => new CourseRatingSummaryResource($this->resource),
            ),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'instructor' => new UserSummaryResource($this->whenLoaded('instructor')),
            'learning_outcomes' => $this->whenLoaded('learningOutcomes', fn () => $this->learningOutcomes->map->only(['id', 'outcome', 'sort_order'])),
            'requirements' => $this->whenLoaded('requirements', fn () => $this->requirements->map->only(['id', 'requirement', 'sort_order'])),
            'target_audiences' => $this->whenLoaded('targetAudiences', fn () => $this->targetAudiences->map->only(['id', 'audience', 'sort_order'])),
            'required_tools' => $this->whenLoaded('requiredTools', fn () => $this->requiredTools->map->only(['id', 'tool', 'sort_order'])),
            'curriculum' => PublicCourseSectionResource::collection($this->whenLoaded('sections')),
            'faqs' => $this->whenLoaded('faqs', fn () => $this->faqs->map(fn ($faq): array => [
                'id' => $faq->id,
                'question_ar' => $faq->question_ar,
                'answer_ar' => $faq->answer_ar,
                'question_en' => $faq->question_en,
                'answer_en' => $faq->answer_en,
                'sort_order' => $faq->sort_order,
                'is_active' => $faq->is_active,
            ])),
            'related_courses' => CourseResource::collection($this->whenLoaded('relatedCourses')),
            'included_in_packages' => PublicPackageResource::collection($this->whenLoaded('publicPackages')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
