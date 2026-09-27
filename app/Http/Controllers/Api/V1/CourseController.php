<?php

namespace App\Http\Controllers\Api\V1;

use App\CourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListCoursesRequest;
use App\Http\Resources\Api\V1\CourseResource;
use App\Models\Course;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ListCoursesRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $courses = Course::query()
            ->where('status', CourseStatus::Published)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->withPublicRatingSummary()
            ->with(['category', 'instructor'])
            ->when($filters['search'] ?? null, fn (Builder $query, string $search) => $query->where(function (Builder $query) use ($search): void {
                $query->where('title', 'like', "%{$search}%")
                    ->orWhere('short_description', 'like', "%{$search}%");
            }))
            ->when($filters['category'] ?? null, fn (Builder $query, string $category) => $query->whereHas('category', fn (Builder $query) => $query->where('id', $category)->orWhere('slug', $category)))
            ->when($filters['instructor'] ?? null, fn (Builder $query, int $instructor) => $query->where('instructor_id', $instructor))
            ->when($filters['level'] ?? null, fn (Builder $query, string $level) => $query->where('level', $level))
            ->when($filters['language'] ?? null, fn (Builder $query, string $language) => $query->where('language', $language))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));

        match ($filters['sort'] ?? 'latest') {
            'oldest' => $courses->oldest('published_at')->orderBy('id'),
            'price_asc' => $courses->orderBy('price')->orderBy('id'),
            'price_desc' => $courses->orderByDesc('price')->orderByDesc('id'),
            'title' => $courses->orderBy('title')->orderBy('id'),
            default => $courses->latest('published_at')->orderByDesc('id'),
        };

        return CourseResource::collection($courses->paginate($request->integer('per_page', 15))->withQueryString());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function show(Course $course): CourseResource
    {
        abort_unless(
            $course->status === CourseStatus::Published
            && $course->published_at !== null
            && $course->published_at->isPast(),
            404,
        );

        $course = Course::query()->withPublicRatingSummary()->findOrFail($course->id);

        return new CourseResource($course->load([
            'category',
            'instructor',
            'learningOutcomes',
            'requirements',
            'targetAudiences',
            'requiredTools',
            'sections' => fn ($query) => $query->where('is_active', true),
            'sections.lessons' => fn ($query) => $query->where('is_published', true),
        ]));
    }
}
