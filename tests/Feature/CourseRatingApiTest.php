<?php

namespace Tests\Feature;

use App\CourseReviewStatus;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\User;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CourseRatingApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_public_reviews_include_only_published_records_without_private_data(): void
    {
        $course = Course::factory()->published()->create();
        $reviewer = User::factory()->create([
            'name' => 'Public Reviewer',
            'email' => 'private@example.test',
            'phone' => '+970599999999',
        ]);
        CourseReview::factory()->published()->for($reviewer)->for($course)->create([
            'rating' => 5,
            'title' => 'Published title',
        ]);
        CourseReview::factory()->for($course)->create(['status' => CourseReviewStatus::Pending]);
        CourseReview::factory()->rejected()->for($course)->create();
        CourseReview::factory()->hidden()->for($course)->create();
        CourseReview::factory()->published()->for($course)->create()->delete();

        $response = $this->getJson("/api/v1/courses/{$course->slug}/reviews")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.rating', 5)
            ->assertJsonPath('data.0.reviewer_name', 'Public Reviewer')
            ->assertJsonMissingPath('data.0.user_id')
            ->assertJsonMissingPath('data.0.email')
            ->assertJsonMissingPath('data.0.phone')
            ->assertJsonMissingPath('data.0.moderation_reason');

        $this->assertSame(
            ['rating', 'title', 'body', 'reviewer_name', 'published_at'],
            array_keys($response->json('data.0')),
        );
    }

    public function test_course_summary_uses_only_published_reviews_with_two_decimal_rounding_and_distribution(): void
    {
        $course = Course::factory()->published()->create();
        foreach ([5, 5, 4] as $rating) {
            CourseReview::factory()->published()->for($course)->create(['rating' => $rating]);
        }
        CourseReview::factory()->for($course)->create(['rating' => 1]);
        CourseReview::factory()->hidden()->for($course)->create(['rating' => 1]);
        CourseReview::factory()->rejected()->for($course)->create(['rating' => 1]);

        $this->getJson("/api/v1/courses/{$course->slug}")
            ->assertOk()
            ->assertJsonPath('data.rating_summary.average_rating', 4.67)
            ->assertJsonPath('data.rating_summary.review_count', 3)
            ->assertJsonPath('data.rating_summary.distribution.rating_1', 0)
            ->assertJsonPath('data.rating_summary.distribution.rating_2', 0)
            ->assertJsonPath('data.rating_summary.distribution.rating_3', 0)
            ->assertJsonPath('data.rating_summary.distribution.rating_4', 1)
            ->assertJsonPath('data.rating_summary.distribution.rating_5', 2);
    }

    public function test_course_without_published_reviews_is_explicitly_unrated(): void
    {
        $course = Course::factory()->published()->create();
        CourseReview::factory()->for($course)->create(['rating' => 5]);

        $this->getJson("/api/v1/courses/{$course->slug}")
            ->assertOk()
            ->assertJsonPath('data.rating_summary.average_rating', null)
            ->assertJsonPath('data.rating_summary.review_count', 0)
            ->assertJsonPath('data.rating_summary.distribution', [
                'rating_1' => 0,
                'rating_2' => 0,
                'rating_3' => 0,
                'rating_4' => 0,
                'rating_5' => 0,
            ]);
    }

    public function test_aggregate_changes_when_review_is_published_hidden_and_restored(): void
    {
        $course = Course::factory()->published()->create();
        $review = CourseReview::factory()->for($course)->create(['rating' => 3]);

        $this->getJson("/api/v1/courses/{$course->slug}")->assertJsonPath('data.rating_summary.review_count', 0);

        $review->update(['status' => CourseReviewStatus::Published, 'published_at' => now()]);
        $this->getJson("/api/v1/courses/{$course->slug}")
            ->assertJsonPath('data.rating_summary.average_rating', 3)
            ->assertJsonPath('data.rating_summary.review_count', 1);

        $review->update(['status' => CourseReviewStatus::Hidden, 'published_at' => null]);
        $this->getJson("/api/v1/courses/{$course->slug}")->assertJsonPath('data.rating_summary.review_count', 0);

        $review->update(['status' => CourseReviewStatus::Published, 'published_at' => now()]);
        $this->getJson("/api/v1/courses/{$course->slug}")->assertJsonPath('data.rating_summary.review_count', 1);
    }

    public function test_public_course_list_query_count_does_not_grow_with_course_count(): void
    {
        Course::factory()->published()->create();
        DB::enableQueryLog();
        $this->getJson('/api/v1/courses')->assertOk();
        $singleCourseQueryCount = count(DB::getQueryLog());

        Course::factory()->count(4)->published()->create();
        DB::flushQueryLog();
        $this->getJson('/api/v1/courses')->assertOk()->assertJsonCount(5, 'data');
        $multipleCourseQueryCount = count(DB::getQueryLog());

        $this->assertSame($singleCourseQueryCount, $multipleCourseQueryCount);
    }
}
