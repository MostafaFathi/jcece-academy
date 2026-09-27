<?php

namespace App\Http\Controllers\Api\V1;

use App\CourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PublicCourseReviewResource;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseReviewController extends Controller
{
    public function index(Request $request, Course $course): AnonymousResourceCollection
    {
        abort_unless(
            $course->status === CourseStatus::Published
            && $course->published_at !== null
            && $course->published_at->isPast(),
            404,
        );

        return PublicCourseReviewResource::collection(
            $course->publishedReviews()
                ->with('user:id,name')
                ->latest('published_at')
                ->orderByDesc('id')
                ->paginate(min(max($request->integer('per_page', 15), 1), 100))
                ->withQueryString(),
        );
    }
}
