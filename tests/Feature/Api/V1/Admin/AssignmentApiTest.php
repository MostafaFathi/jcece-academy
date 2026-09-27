<?php

namespace Tests\Feature\Api\V1\Admin;

use App\AssignmentStatus;
use App\AssignmentSubmissionType;
use App\Models\Assignment;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\User;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssignmentApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('managerRoles')]
    public function test_authorized_staff_create_update_publish_unpublish_and_archive_assignment(string $role): void
    {
        $this->authenticateAs($role);
        $course = Course::factory()->create();
        $section = CourseSection::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($section, 'section')->create();

        $response = $this->postJson("/api/v1/admin/courses/{$course->id}/assignments", [
            'lesson_id' => $lesson->id,
            'title' => 'Structural Analysis Report',
            'submission_type' => AssignmentSubmissionType::TextAndFile->value,
            'maximum_score' => 100,
            'passing_score' => 60,
            'max_attempts' => 2,
            'allow_late_submissions' => true,
        ])->assertCreated()->assertJsonPath('data.status', AssignmentStatus::Draft->value);
        $assignmentId = $response->json('data.id');

        $this->patchJson("/api/v1/admin/courses/{$course->id}/assignments/{$assignmentId}", [
            'title' => 'Updated Structural Report',
            'maximum_score' => 120,
            'passing_score' => 72,
        ])->assertOk()->assertJsonPath('data.maximum_score', '120.00');
        $this->postJson("/api/v1/admin/assignments/{$assignmentId}/publication")
            ->assertOk()->assertJsonPath('data.status', AssignmentStatus::Published->value);
        $this->deleteJson("/api/v1/admin/assignments/{$assignmentId}/publication")
            ->assertOk()->assertJsonPath('data.status', AssignmentStatus::Draft->value);
        $this->deleteJson("/api/v1/admin/courses/{$course->id}/assignments/{$assignmentId}")
            ->assertOk()->assertJsonPath('data.status', AssignmentStatus::Archived->value);
        $this->postJson("/api/v1/admin/assignments/{$assignmentId}/publication")
            ->assertUnprocessable()->assertJsonValidationErrors('assignment');

        $this->assertDatabaseHas('assignments', ['id' => $assignmentId, 'title' => 'Updated Structural Report', 'status' => AssignmentStatus::Archived->value]);
    }

    public function test_returns_422_for_cross_course_lesson_invalid_scores_and_dates(): void
    {
        $this->authenticateAs(RoleName::Admin->value);
        $course = Course::factory()->create();
        $otherLesson = Lesson::factory()->create();

        $this->postJson("/api/v1/admin/courses/{$course->id}/assignments", [
            'lesson_id' => $otherLesson->id,
            'title' => 'Invalid',
            'submission_type' => AssignmentSubmissionType::Text->value,
            'maximum_score' => 50,
            'passing_score' => 51,
            'max_attempts' => 0,
            'available_from' => '2026-10-02 10:00:00',
            'due_at' => '2026-10-01 10:00:00',
        ])->assertUnprocessable()->assertJsonValidationErrors(['lesson_id', 'passing_score', 'max_attempts', 'due_at']);

        $this->assertDatabaseCount('assignments', 0);
    }

    public function test_update_validates_scores_and_dates_against_existing_values(): void
    {
        $this->authenticateAs(RoleName::Admin->value);
        $assignment = Assignment::factory()->create([
            'maximum_score' => '100.00', 'passing_score' => '60.00',
            'available_from' => '2026-10-01 10:00:00', 'due_at' => '2026-10-02 10:00:00',
        ]);

        $this->patchJson("/api/v1/admin/courses/{$assignment->course_id}/assignments/{$assignment->id}", [
            'maximum_score' => 50,
            'due_at' => '2026-09-30 10:00:00',
        ])->assertUnprocessable()->assertJsonValidationErrors(['passing_score', 'due_at']);
    }

    public function test_student_cannot_manage_assignments(): void
    {
        $this->authenticateAs(RoleName::Student->value);
        $course = Course::factory()->create();

        $this->postJson("/api/v1/admin/courses/{$course->id}/assignments", [
            'title' => 'Forbidden',
            'submission_type' => AssignmentSubmissionType::Text->value,
            'maximum_score' => 100,
        ])->assertForbidden();

        $this->assertDatabaseMissing('assignments', ['title' => 'Forbidden']);
    }

    /** @return array<string, array{string}> */
    public static function managerRoles(): array
    {
        return ['admin' => [RoleName::Admin->value], 'content manager' => [RoleName::ContentManager->value]];
    }

    private function authenticateAs(string $role): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole($role);
        Sanctum::actingAs($user);

        return $user;
    }
}
