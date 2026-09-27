<?php

namespace App\Http\Controllers\Api\V1\Me;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Me\StoreCourseReviewRequest;
use App\Http\Requests\Api\V1\Me\UpdateCourseReviewRequest;
use App\Http\Resources\Api\V1\StudentCourseReviewResource;
use App\Models\Course;
use App\Models\CourseReview;
use App\Services\CourseReviewSubmissionService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CourseReviewController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return StudentCourseReviewResource::collection(
            CourseReview::query()
                ->whereBelongsTo(request()->user())
                ->with('course:id,title,slug')
                ->latest('submitted_at')
                ->orderByDesc('id')
                ->paginate(),
        );
    }

    public function show(Course $course): StudentCourseReviewResource
    {
        $review = CourseReview::query()
            ->whereBelongsTo(request()->user())
            ->whereBelongsTo($course)
            ->with('course:id,title,slug')
            ->firstOrFail();
        Gate::authorize('view', $review);

        return new StudentCourseReviewResource($review);
    }

    public function store(
        StoreCourseReviewRequest $request,
        Course $course,
        CourseReviewSubmissionService $submissions,
    ): StudentCourseReviewResource {
        Gate::authorize('create', CourseReview::class);
        $review = $submissions->create($request->user(), $course, $request->validated());

        return new StudentCourseReviewResource($review->load('course:id,title,slug'));
    }

    public function update(
        UpdateCourseReviewRequest $request,
        CourseReview $review,
        CourseReviewSubmissionService $submissions,
    ): StudentCourseReviewResource {
        abort_unless($review->user_id === $request->user()->id, 404);
        Gate::authorize('update', $review);
        $review = $submissions->update($request->user(), $review, $request->validated());

        return new StudentCourseReviewResource($review->load('course:id,title,slug'));
    }
}
