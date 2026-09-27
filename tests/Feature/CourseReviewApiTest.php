<?php

namespace Tests\Feature;

use App\CourseReviewStatus;
use App\EnrollmentStatus;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CourseReviewApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_eligible_student_creates_lists_and_views_pending_review_without_setting_admin_fields(): void
    {
        [$user, $course] = $this->eligibleStudent();
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/me/courses/{$course->slug}/reviews", [
            'rating' => 5,
            'title' => 'Excellent practical course',
            'body' => 'The examples were clear and useful.',
            'status' => CourseReviewStatus::Published->value,
            'published_at' => now(),
            'moderated_by' => User::factory()->create()->id,
            'moderation_reason' => 'Client controlled',
        ])->assertCreated()
            ->assertJsonPath('data.status', CourseReviewStatus::Pending->value)
            ->assertJsonPath('data.rating', 5);
        $reviewId = $response->json('data.id');

        $this->assertDatabaseHas('course_reviews', [
            'id' => $reviewId,
            'user_id' => $user->id,
            'course_id' => $course->id,
            'status' => CourseReviewStatus::Pending->value,
            'published_at' => null,
            'moderated_by' => null,
            'moderation_reason' => null,
        ]);
        $this->assertDatabaseHas('course_review_histories', [
            'course_review_id' => $reviewId,
            'action' => 'submitted',
            'to_status' => CourseReviewStatus::Pending->value,
        ]);
        $this->getJson('/api/v1/me/reviews')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson("/api/v1/me/courses/{$course->slug}/review")
            ->assertOk()
            ->assertJsonPath('data.id', $reviewId);
    }

    public function test_unauthenticated_student_cannot_create_review(): void
    {
        $course = Course::factory()->published()->create();

        $this->postJson("/api/v1/me/courses/{$course->slug}/reviews", ['rating' => 5])
            ->assertUnauthorized();

        $this->assertDatabaseCount('course_reviews', 0);
    }

    #[DataProvider('ineligibleEnrollmentStates')]
    public function test_missing_expired_or_suspended_access_returns_forbidden(string $state): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();

        if ($state !== 'missing') {
            $enrollmentFactory = Enrollment::factory()->for($user)->for($course);
            $enrollment = $state === 'suspended'
                ? $enrollmentFactory->suspended()->create()
                : $enrollmentFactory->create();
            $grantFactory = EnrollmentAccessGrant::factory()->for($enrollment);
            $state === 'expired' ? $grantFactory->expired()->create() : $grantFactory->lifetime()->create();
        }

        Sanctum::actingAs($user);

        $this->postJson("/api/v1/me/courses/{$course->slug}/reviews", ['rating' => 4])
            ->assertForbidden();
        $this->assertDatabaseCount('course_reviews', 0);
    }

    public function test_student_cannot_create_second_review_for_same_course(): void
    {
        [$user, $course] = $this->eligibleStudent();
        CourseReview::factory()->for($user)->for($course)->create();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/me/courses/{$course->slug}/reviews", ['rating' => 4])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('course');

        $this->assertDatabaseCount('course_reviews', 1);
    }

    public function test_database_unique_constraint_prevents_concurrent_duplicate_reviews(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->create();
        CourseReview::factory()->for($user)->for($course)->create();

        $this->expectException(QueryException::class);
        CourseReview::factory()->for($user)->for($course)->create();
    }

    #[DataProvider('invalidRatings')]
    public function test_rating_outside_one_through_five_is_rejected(int $rating): void
    {
        [$user, $course] = $this->eligibleStudent();
        Sanctum::actingAs($user);

        $this->postJson("/api/v1/me/courses/{$course->slug}/reviews", ['rating' => $rating])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('rating');

        $this->assertDatabaseCount('course_reviews', 0);
    }

    public function test_student_cannot_view_or_edit_another_students_review(): void
    {
        [$owner, $course] = $this->eligibleStudent();
        $review = CourseReview::factory()->for($owner)->for($course)->create(['rating' => 5]);
        $otherStudent = User::factory()->create();
        Sanctum::actingAs($otherStudent);

        $this->getJson("/api/v1/me/courses/{$course->slug}/review")->assertNotFound();
        $this->patchJson("/api/v1/me/reviews/{$review->id}", ['rating' => 1])->assertNotFound();
        $this->assertSame(5, $review->fresh()->rating);
    }

    public function test_student_edit_resubmits_published_review_and_preserves_history(): void
    {
        [$user, $course] = $this->eligibleStudent();
        $review = CourseReview::factory()->published()->for($user)->for($course)->create([
            'rating' => 5,
            'title' => 'Original title',
            'body' => 'Original body',
        ]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/me/reviews/{$review->id}", [
            'rating' => 3,
            'title' => 'Updated title',
            'body' => 'Updated body',
            'status' => CourseReviewStatus::Published->value,
        ])->assertOk()
            ->assertJsonPath('data.status', CourseReviewStatus::Pending->value)
            ->assertJsonPath('data.rating', 3);

        $this->assertDatabaseHas('course_review_histories', [
            'course_review_id' => $review->id,
            'action' => 'resubmitted',
            'from_status' => CourseReviewStatus::Published->value,
            'to_status' => CourseReviewStatus::Pending->value,
            'old_rating' => 5,
            'new_rating' => 3,
            'old_title' => 'Original title',
            'new_title' => 'Updated title',
        ]);
        $this->getJson("/api/v1/courses/{$course->slug}/reviews")->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_expired_access_preserves_published_review_but_prevents_new_edit(): void
    {
        [$user, $course, $enrollment] = $this->eligibleStudent();
        $review = CourseReview::factory()->published()->for($user)->for($course)->create();
        $enrollment->accessGrants()->update(['access_expires_at' => now()->subMinute()]);
        Sanctum::actingAs($user);

        $this->patchJson("/api/v1/me/reviews/{$review->id}", ['rating' => 2])->assertForbidden();
        $this->getJson("/api/v1/courses/{$course->slug}/reviews")->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame(CourseReviewStatus::Published, $review->fresh()->status);
    }

    /** @return array<string, array{string}> */
    public static function ineligibleEnrollmentStates(): array
    {
        return [
            'missing enrollment' => ['missing'],
            'expired access' => ['expired'],
            'suspended enrollment' => ['suspended'],
        ];
    }

    /** @return array<string, array{int}> */
    public static function invalidRatings(): array
    {
        return ['zero' => [0], 'six' => [6]];
    }

    /** @return array{User, Course, Enrollment} */
    private function eligibleStudent(): array
    {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($user)->for($course)->create([
            'status' => EnrollmentStatus::Active,
        ]);
        EnrollmentAccessGrant::factory()->for($enrollment)->lifetime()->create();

        return [$user, $course, $enrollment];
    }
}
