<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Lesson;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use App\QuizStatus;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuizApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    #[DataProvider('managerRoles')]
    public function test_authorized_staff_create_update_and_archive_quiz(string $role): void
    {
        $this->authenticateAs($role);
        $course = Course::factory()->create();
        $section = CourseSection::factory()->for($course)->create();
        $lesson = Lesson::factory()->for($section, 'section')->create();

        $response = $this->postJson("/api/v1/admin/courses/{$course->id}/quizzes", [
            'lesson_id' => $lesson->id,
            'title' => 'BIM Fundamentals Assessment',
            'passing_score' => 70,
            'time_limit_minutes' => 30,
            'max_attempts' => 2,
            'show_results' => true,
        ])->assertCreated()
            ->assertJsonPath('data.status', QuizStatus::Draft->value);
        $quizId = $response->json('data.id');

        $this->patchJson("/api/v1/admin/courses/{$course->id}/quizzes/{$quizId}", [
            'title' => 'Updated Assessment',
            'passing_score' => 75,
        ])->assertOk()->assertJsonPath('data.title', 'Updated Assessment');

        $this->deleteJson("/api/v1/admin/courses/{$course->id}/quizzes/{$quizId}")
            ->assertOk()->assertJsonPath('data.status', QuizStatus::Archived->value);

        $this->assertDatabaseHas('quizzes', [
            'id' => $quizId,
            'status' => QuizStatus::Archived->value,
            'passing_score' => 75,
        ]);
    }

    public function test_student_cannot_manage_quizzes(): void
    {
        $this->authenticateAs(RoleName::Student->value);
        $course = Course::factory()->create();

        $this->postJson("/api/v1/admin/courses/{$course->id}/quizzes", [
            'title' => 'Forbidden',
            'passing_score' => 60,
        ])->assertForbidden();

        $this->assertDatabaseMissing('quizzes', ['title' => 'Forbidden']);
    }

    public function test_quiz_rejects_cross_course_lesson_invalid_scores_and_availability_window(): void
    {
        $this->authenticateAs(RoleName::Admin->value);
        $course = Course::factory()->create();
        $otherLesson = Lesson::factory()->create();

        $this->postJson("/api/v1/admin/courses/{$course->id}/quizzes", [
            'lesson_id' => $otherLesson->id,
            'title' => 'Invalid Quiz',
            'passing_score' => 101,
            'time_limit_minutes' => 0,
            'max_attempts' => 0,
            'available_from' => '2026-11-02 10:00:00',
            'available_until' => '2026-11-01 10:00:00',
        ])->assertUnprocessable()
            ->assertJsonValidationErrors([
                'lesson_id',
                'passing_score',
                'time_limit_minutes',
                'max_attempts',
                'available_until',
            ]);

        $this->assertDatabaseCount('quizzes', 0);
    }

    public function test_publication_requires_valid_question_and_supports_unpublish(): void
    {
        $this->authenticateAs(RoleName::ContentManager->value);
        $quiz = Quiz::factory()->create();

        $this->postJson("/api/v1/admin/quizzes/{$quiz->id}/publication")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('quiz');
        $question = QuizQuestion::factory()->for($quiz)->create();
        QuizOption::factory()->for($question, 'question')->create(['is_correct' => true, 'sort_order' => 0]);

        $this->postJson("/api/v1/admin/quizzes/{$quiz->id}/publication")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('options');

        QuizOption::factory()->for($question, 'question')->create(['is_correct' => false, 'sort_order' => 1]);

        $this->postJson("/api/v1/admin/quizzes/{$quiz->id}/publication")
            ->assertOk()->assertJsonPath('data.status', QuizStatus::Published->value);
        $this->deleteJson("/api/v1/admin/quizzes/{$quiz->id}/publication")
            ->assertOk()->assertJsonPath('data.status', QuizStatus::Draft->value);

        $this->assertSame(QuizStatus::Draft, $quiz->fresh()->status);
    }

    public function test_authorized_staff_inspect_attempts_while_student_is_forbidden(): void
    {
        $quiz = Quiz::factory()->create();
        $attempt = QuizAttempt::factory()->for($quiz)->create();
        $this->authenticateAs(RoleName::ContentManager->value);

        $this->getJson("/api/v1/admin/quizzes/{$quiz->id}/attempts")
            ->assertOk()->assertJsonPath('data.0.id', $attempt->id);

        $this->authenticateAs(RoleName::Student->value);
        $this->getJson("/api/v1/admin/quizzes/{$quiz->id}/attempts")->assertForbidden();
    }

    /** @return array<string, array{string}> */
    public static function managerRoles(): array
    {
        return [
            'admin' => [RoleName::Admin->value],
            'content manager' => [RoleName::ContentManager->value],
        ];
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
