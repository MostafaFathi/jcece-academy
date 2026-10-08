<?php

namespace App\Models;

use App\CourseLevel;
use App\CourseReviewStatus;
use App\CourseStatus;
use App\CourseTrainingType;
use App\PackageStatus;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['category_id', 'instructor_id', 'title', 'slug', 'short_description', 'description', 'thumbnail', 'promo_video_url', 'level', 'training_type', 'language', 'duration_minutes', 'access_duration_days', 'price', 'compare_price', 'promotional_price', 'discount_starts_at', 'discount_ends_at', 'certificate_enabled', 'discussion_enabled', 'status', 'is_featured', 'published_at', 'created_by', 'updated_by'])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory, SoftDeletes;

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function instructor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'instructor_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function learningOutcomes(): HasMany
    {
        return $this->hasMany(CourseLearningOutcome::class)->orderBy('sort_order')->orderBy('id');
    }

    public function requirements(): HasMany
    {
        return $this->hasMany(CourseRequirement::class)->orderBy('sort_order')->orderBy('id');
    }

    public function targetAudiences(): HasMany
    {
        return $this->hasMany(CourseTargetAudience::class)->orderBy('sort_order')->orderBy('id');
    }

    public function requiredTools(): HasMany
    {
        return $this->hasMany(CourseRequiredTool::class)->orderBy('sort_order')->orderBy('id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(CourseSection::class)->orderBy('sort_order')->orderBy('id');
    }

    public function lessons(): HasManyThrough
    {
        return $this->hasManyThrough(Lesson::class, CourseSection::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function quizzes(): HasMany
    {
        return $this->hasMany(Quiz::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    public function certificates(): HasMany
    {
        return $this->hasMany(Certificate::class);
    }

    public function finalExamQuiz(): BelongsTo
    {
        return $this->belongsTo(Quiz::class, 'certificate_final_exam_quiz_id');
    }

    public function requiredCertificateAssignments(): BelongsToMany
    {
        return $this->belongsToMany(Assignment::class, 'course_certificate_required_assignments')->withTimestamps();
    }

    public function certificateApprovalRequests(): HasMany
    {
        return $this->hasMany(CertificateApprovalRequest::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(CourseReview::class);
    }

    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class, 'related_course_id');
    }

    public function publishedReviews(): HasMany
    {
        return $this->hasMany(CourseReview::class)
            ->where('status', CourseReviewStatus::Published)
            ->whereNotNull('published_at');
    }

    public function faqs(): HasMany
    {
        return $this->hasMany(CourseFaq::class)->orderBy('sort_order')->orderBy('id');
    }

    #[Scope]
    protected function withPublicRatingSummary(Builder $query): Builder
    {
        return $query
            ->withCount('publishedReviews')
            ->withAvg('publishedReviews as published_reviews_average_rating', 'rating')
            ->withCount([
                'publishedReviews as published_reviews_rating_1_count' => fn (Builder $query) => $query->where('rating', 1),
                'publishedReviews as published_reviews_rating_2_count' => fn (Builder $query) => $query->where('rating', 2),
                'publishedReviews as published_reviews_rating_3_count' => fn (Builder $query) => $query->where('rating', 3),
                'publishedReviews as published_reviews_rating_4_count' => fn (Builder $query) => $query->where('rating', 4),
                'publishedReviews as published_reviews_rating_5_count' => fn (Builder $query) => $query->where('rating', 5),
            ]);
    }

    public function packageMemberships(): HasMany
    {
        return $this->hasMany(PackageCourse::class);
    }

    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_courses')
            ->as('membership')
            ->withPivot(['id', 'sort_order', 'is_required'])
            ->withTimestamps();
    }

    public function publicPackages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class, 'package_courses')
            ->where('packages.status', PackageStatus::Published->value)
            ->whereNotNull('packages.published_at')
            ->where('packages.published_at', '<=', now())
            ->withPivot(['id', 'sort_order', 'is_required']);
    }

    public function relatedCourses(): HasMany
    {
        return $this->hasMany(Course::class, 'category_id', 'category_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'level' => CourseLevel::class,
            'training_type' => CourseTrainingType::class,
            'status' => CourseStatus::class,
            'price' => 'decimal:2',
            'compare_price' => 'decimal:2',
            'promotional_price' => 'decimal:2',
            'discount_starts_at' => 'datetime',
            'discount_ends_at' => 'datetime',
            'certificate_enabled' => 'boolean',
            'certificate_required_lesson_percentage' => 'decimal:2',
            'certificate_final_exam_required' => 'boolean',
            'certificate_final_exam_passing_percentage' => 'decimal:2',
            'certificate_admin_approval_required' => 'boolean',
            'certificate_requirements_version' => 'integer',
            'discussion_enabled' => 'boolean',
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
