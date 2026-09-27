<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\RejectCourseReviewRequest;
use App\Http\Resources\Api\V1\AdminCourseReviewResource;
use App\Models\CourseReview;
use App\Services\CourseReviewModerationService;
use Illuminate\Support\Facades\Gate;

class CourseReviewRejectionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(RejectCourseReviewRequest $request, CourseReview $review, CourseReviewModerationService $moderation): AdminCourseReviewResource
    {
        Gate::authorize('moderate', $review);
        $review = $moderation->reject($review, $request->user(), $request->validated('reason'));

        return new AdminCourseReviewResource($review->load(['course:id,title,slug', 'user:id,name', 'moderator:id,name', 'histories']));
    }
}
