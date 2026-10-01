<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\CourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\StoreCourseRequest;
use App\Http\Requests\Api\V1\Admin\UpdateCourseRequest;
use App\Http\Requests\Api\V1\ListCoursesRequest;
use App\Http\Resources\Api\V1\CourseResource;
use App\Models\Course;
use App\Services\CourseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ListCoursesRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Course::class);

        $filters = $request->validated();
        $courses = Course::query()
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
            'oldest' => $courses->oldest()->orderBy('id'),
            'price_asc' => $courses->orderBy('price')->orderBy('id'),
            'price_desc' => $courses->orderByDesc('price')->orderByDesc('id'),
            'title' => $courses->orderBy('title')->orderBy('id'),
            default => $courses->latest()->orderByDesc('id'),
        };

        return CourseResource::collection($courses->paginate($request->integer('per_page', 25))->withQueryString());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreCourseRequest $request, CourseService $courseService): JsonResponse
    {
        Gate::authorize('create', Course::class);
        $attributes = $request->validated();
        Gate::authorize('assignInstructor', [Course::class, (int) $attributes['instructor_id']]);

        if (($attributes['status'] ?? CourseStatus::Draft->value) === CourseStatus::Published->value) {
            Gate::authorize('publish', new Course);
        }

        $course = $courseService->create($attributes, $request->user());

        return (new CourseResource($course))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(Course $course): CourseResource
    {
        Gate::authorize('view', $course);

        return new CourseResource($course->load([
            'category',
            'instructor',
            'learningOutcomes',
            'requirements',
            'targetAudiences',
            'requiredTools',
        ]));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateCourseRequest $request, Course $course, CourseService $courseService): CourseResource
    {
        Gate::authorize('update', $course);
        $attributes = $request->validated();

        if (array_key_exists('instructor_id', $attributes)) {
            Gate::authorize('assignInstructor', [Course::class, (int) $attributes['instructor_id']]);
        }

        if (($attributes['status'] ?? null) === CourseStatus::Published->value && $course->status !== CourseStatus::Published) {
            Gate::authorize('publish', $course);
        }

        $course = $courseService->update($course, $attributes, $request->user());

        return new CourseResource($course);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Course $course): Response
    {
        Gate::authorize('delete', $course);

        $course->delete();

        return response()->noContent();
    }
}
