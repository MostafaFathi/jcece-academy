<?php

namespace Tests\Feature;

use App\CourseReviewStatus;
use App\Models\CourseReview;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CourseReviewModerationApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_content_manager_lists_inspects_publishes_hides_and_restores_review_with_audit_history(): void
    {
        $manager = $this->authenticateAs(RoleName::ContentManager);
        $review = CourseReview::factory()->create(['rating' => 4]);

        $this->getJson('/api/v1/admin/course-reviews')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.status', CourseReviewStatus::Pending->value);
        $this->getJson("/api/v1/admin/course-reviews/{$review->id}")->assertOk();

        $this->postJson("/api/v1/admin/course-reviews/{$review->id}/publication")
            ->assertOk()
            ->assertJsonPath('data.status', CourseReviewStatus::Published->value)
            ->assertJsonPath('data.moderator.id', $manager->id);
        $this->postJson("/api/v1/admin/course-reviews/{$review->id}/hiding", [
            'reason' => 'Contains claims requiring investigation.',
        ])->assertOk()
            ->assertJsonPath('data.status', CourseReviewStatus::Hidden->value)
            ->assertJsonPath('data.moderation_reason', 'Contains claims requiring investigation.');
        $this->postJson("/api/v1/admin/course-reviews/{$review->id}/publication")
            ->assertOk()
            ->assertJsonPath('data.status', CourseReviewStatus::Published->value);

        $this->assertDatabaseHas('course_review_histories', [
            'course_review_id' => $review->id,
            'actor_id' => $manager->id,
            'action' => 'published',
            'from_status' => CourseReviewStatus::Pending->value,
            'to_status' => CourseReviewStatus::Published->value,
        ]);
        $this->assertDatabaseHas('course_review_histories', [
            'course_review_id' => $review->id,
            'action' => 'hidden',
            'reason' => 'Contains claims requiring investigation.',
        ]);
        $this->assertDatabaseHas('course_review_histories', [
            'course_review_id' => $review->id,
            'action' => 'restored',
            'from_status' => CourseReviewStatus::Hidden->value,
            'to_status' => CourseReviewStatus::Published->value,
        ]);
    }

    public function test_rejection_requires_reason_and_records_moderator_identity(): void
    {
        $manager = $this->authenticateAs(RoleName::Admin);
        $review = CourseReview::factory()->create();

        $this->postJson("/api/v1/admin/course-reviews/{$review->id}/rejection", [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/admin/course-reviews/{$review->id}/rejection", [
            'reason' => 'Review violates the publication guidelines.',
        ])->assertOk()
            ->assertJsonPath('data.status', CourseReviewStatus::Rejected->value)
            ->assertJsonPath('data.moderator.id', $manager->id);

        $this->assertDatabaseHas('course_reviews', [
            'id' => $review->id,
            'status' => CourseReviewStatus::Rejected->value,
            'moderated_by' => $manager->id,
            'moderation_reason' => 'Review violates the publication guidelines.',
        ]);
    }

    public function test_invalid_moderation_transition_returns_validation_error(): void
    {
        $this->authenticateAs(RoleName::Admin);
        $review = CourseReview::factory()->rejected()->create();

        $this->postJson("/api/v1/admin/course-reviews/{$review->id}/publication")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('status');
    }

    public function test_instructor_and_student_cannot_moderate_reviews(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $review = CourseReview::factory()->create();

        foreach ([RoleName::Instructor, RoleName::Student] as $role) {
            $user = User::factory()->create();
            $user->assignRole($role->value);
            Sanctum::actingAs($user);

            $this->getJson('/api/v1/admin/course-reviews')->assertForbidden();
            $this->postJson("/api/v1/admin/course-reviews/{$review->id}/publication")->assertForbidden();
        }

        $this->assertSame(CourseReviewStatus::Pending, $review->fresh()->status);
    }

    private function authenticateAs(RoleName $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role->value);
        Sanctum::actingAs($user);

        return $user;
    }
}
