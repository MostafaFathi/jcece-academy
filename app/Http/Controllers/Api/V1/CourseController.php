<?php

namespace App\Http\Controllers\Api\V1;

use App\CourseReviewStatus;
use App\CourseStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ListCoursesRequest;
use App\Http\Resources\Api\V1\CourseResource;
use App\Models\Course;
use App\Models\OrderItem;
use App\OrderStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CourseController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(ListCoursesRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();
        $priceExpression = 'CASE WHEN promotional_price IS NOT NULL AND discount_starts_at <= ? AND discount_ends_at > ? THEN promotional_price ELSE price END';
        $priceBindings = [now(), now()];
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
            ->when($filters['training_type'] ?? null, fn (Builder $query, string $trainingType) => $query->where('training_type', $trainingType))
            ->when($filters['price_type'] ?? null, fn (Builder $query, string $priceType) => $query->whereRaw($priceExpression.($priceType === 'free' ? ' = 0' : ' > 0'), $priceBindings))
            ->when($filters['rating_min'] ?? null, fn (Builder $query, int $rating) => $query->whereRaw('(SELECT AVG(rating) FROM course_reviews WHERE course_reviews.course_id = courses.id AND course_reviews.status = ? AND course_reviews.published_at IS NOT NULL AND course_reviews.deleted_at IS NULL) >= ?', [CourseReviewStatus::Published->value, $rating]))
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status));

        if (($filters['sort'] ?? null) === 'bestseller') {
            $courses->addSelect(['completed_sales_count' => OrderItem::query()
                ->selectRaw('COALESCE(SUM(quantity), 0)')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->whereColumn('order_items.purchasable_id', 'courses.id')
                ->where('order_items.purchasable_type', 'course')
                ->where('orders.status', OrderStatus::Completed->value)]);
        }

        match ($filters['sort'] ?? 'latest') {
            'oldest' => $courses->oldest('published_at')->orderBy('id'),
            'price_asc' => $courses->orderByRaw($priceExpression.' ASC', $priceBindings)->orderBy('id'),
            'price_desc' => $courses->orderByRaw($priceExpression.' DESC', $priceBindings)->orderByDesc('id'),
            'rating' => $courses->orderByDesc('published_reviews_average_rating')->orderByDesc('published_reviews_count')->orderBy('id'),
            'bestseller' => $courses->orderByDesc('completed_sales_count')->orderByDesc('published_at')->orderByDesc('id'),
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
            'faqs' => fn (HasMany $query) => $query->where('is_active', true),
            'relatedCourses' => fn (HasMany $query) => $query
                ->where('courses.id', '!=', $course->id)
                ->where('status', CourseStatus::Published)
                ->whereNotNull('published_at')
                ->where('published_at', '<=', now())
                ->withPublicRatingSummary()
                ->with(['category', 'instructor'])
                ->limit(4),
            'publicPackages',
        ]));
    }
}
