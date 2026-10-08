<?php

namespace App\Http\Controllers\Api\V1;

use App\CourseReviewStatus;
use App\CourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\PublicCourseReviewResource;
use App\Models\CourseReview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TestimonialController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        $reviews = CourseReview::query()
            ->where('status', CourseReviewStatus::Published)
            ->whereNotNull('published_at')
            ->whereHas('course', fn (Builder $query) => $query
                ->where('status', CourseStatus::Published)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now()))
            ->with(['user', 'course'])
            ->latest('published_at')->orderByDesc('id')->paginate(6);

        return PublicCourseReviewResource::collection($reviews);
    }
}
