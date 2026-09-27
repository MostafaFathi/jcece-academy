<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\CourseReviewStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ListCourseReviewsRequest;
use App\Http\Resources\Api\V1\AdminCourseReviewResource;
use App\Models\CourseReview;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CourseReviewController extends Controller
{
    public function index(ListCourseReviewsRequest $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', CourseReview::class);
        $filters = $request->validated();

        return AdminCourseReviewResource::collection(
            CourseReview::query()
                ->with(['course:id,title,slug', 'user:id,name', 'moderator:id,name'])
                ->where('status', $filters['status'] ?? CourseReviewStatus::Pending)
                ->when($filters['course_id'] ?? null, fn ($query, int $courseId) => $query->where('course_id', $courseId))
                ->when($filters['user_id'] ?? null, fn ($query, int $userId) => $query->where('user_id', $userId))
                ->latest('submitted_at')
                ->orderByDesc('id')
                ->paginate($filters['per_page'] ?? 25)
                ->withQueryString(),
        );
    }

    public function show(CourseReview $review): AdminCourseReviewResource
    {
        Gate::authorize('view', $review);

        return new AdminCourseReviewResource($review->load([
            'course:id,title,slug',
            'user:id,name',
            'moderator:id,name',
            'histories',
        ]));
    }
}
