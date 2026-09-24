<?php

namespace Tests\Feature\Api\V1\Me;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use App\QuizStatus;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuizAccessApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_student_quiz_request_returns_401(): void
    {
        $this->getJson('/api/v1/me/quizzes')->assertUnauthorized();
    }

    public function test_student_with_effective_access_lists_and_views_available_quiz_without_answer_key(): void
    {
        [$user, , $quiz] = $this->createAccessibleQuiz();
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/me/quizzes')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $quiz->id)
            ->assertJsonMissingPath('data.0.status')
            ->assertJsonMissingPath('data.0.show_correct_answers');

        $response = $this->getJson("/api/v1/me/quizzes/{$quiz->id}")
            ->assertOk()
            ->assertJsonMissingPath('data.questions');

        $this->assertStringNotContainsString('is_correct', $response->getContent());
        $this->assertStringNotContainsString('Secret explanation', $response->getContent());
    }

    #[DataProvider('blockedAccessStates')]
    public function test_missing_expired_or_suspended_access_returns_403_without_quiz_content(string $state): void
    {
        [$user, , $quiz, $enrollment] = $this->createAccessibleQuiz();

        if ($state === 'missing') {
            $enrollment->accessGrants()->delete();
            $enrollment->delete();
        } elseif ($state === 'expired') {
            $enrollment->accessGrants()->delete();
            EnrollmentAccessGrant::factory()->expired()->for($enrollment)->create();
        } else {
            $enrollment->update(['status' => 'suspended']);
        }
        Sanctum::actingAs($user);

        $response = $this->getJson("/api/v1/me/quizzes/{$quiz->id}")->assertForbidden();

        $this->assertStringNotContainsString($quiz->title, $response->getContent());
    }

    #[DataProvider('unavailableQuizStates')]
    public function test_unpublished_or_outside_availability_window_returns_404(string $state): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, , $quiz] = $this->createAccessibleQuiz();
        $attributes = match ($state) {
            'draft' => ['status' => QuizStatus::Draft],
            'future' => ['available_from' => now()->addMinute()],
            'ended' => ['available_until' => now()->subMinute()],
        };
        $quiz->update($attributes);
        Sanctum::actingAs($user);

        $this->getJson("/api/v1/me/quizzes/{$quiz->id}")->assertNotFound();
        $this->getJson('/api/v1/me/quizzes')->assertOk()->assertJsonCount(0, 'data');
    }

    /** @return array<string, array{string}> */
    public static function blockedAccessStates(): array
    {
        return [
            'missing grant' => ['missing'],
            'expired grant' => ['expired'],
            'suspended enrollment' => ['suspended'],
        ];
    }

    /** @return array<string, array{string}> */
    public static function unavailableQuizStates(): array
    {
        return [
            'draft quiz' => ['draft'],
            'future quiz' => ['future'],
            'ended quiz' => ['ended'],
        ];
    }

    /** @return array{User, Course, Quiz, Enrollment} */
    private function createAccessibleQuiz(): array
    {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($user)->for($course)->create();
        EnrollmentAccessGrant::factory()->lifetime()->for($enrollment)->create();
        $quiz = Quiz::factory()->published()->for($course)->create(['title' => 'Protected Assessment']);
        $question = QuizQuestion::factory()->for($quiz)->create(['explanation' => 'Secret explanation']);
        QuizOption::factory()->for($question, 'question')->create(['is_correct' => true]);
        QuizOption::factory()->for($question, 'question')->create(['is_correct' => false, 'sort_order' => 1]);

        return [$user, $course, $quiz, $enrollment];
    }
}
