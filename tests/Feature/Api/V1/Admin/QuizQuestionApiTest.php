<?php

namespace Tests\Feature\Api\V1\Admin;

use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use App\QuizQuestionType;
use App\QuizStatus;
use App\RoleName;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class QuizQuestionApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_content_manager_creates_question_and_zero_based_options(): void
    {
        $this->authenticate();
        $quiz = Quiz::factory()->create();

        $response = $this->postJson("/api/v1/admin/quizzes/{$quiz->id}/questions", [
            'type' => QuizQuestionType::MultipleChoice->value,
            'question_text' => 'Select the BIM applications.',
            'points' => '2.50',
            'options' => [
                ['answer_text' => 'Revit', 'is_correct' => true],
                ['answer_text' => 'Navisworks', 'is_correct' => true],
                ['answer_text' => 'Notepad', 'is_correct' => false],
            ],
        ])->assertCreated()
            ->assertJsonPath('data.options.0.sort_order', 0)
            ->assertJsonPath('data.options.1.sort_order', 1)
            ->assertJsonPath('data.options.2.sort_order', 2);

        $this->assertDatabaseHas('quiz_questions', ['id' => $response->json('data.id'), 'points' => 2.5]);
        $this->assertDatabaseCount('quiz_options', 3);
    }

    #[DataProvider('invalidConfigurations')]
    public function test_invalid_answer_configuration_returns_422_without_partial_question(string $type, array $options): void
    {
        $this->authenticate();
        $quiz = Quiz::factory()->create();

        $this->postJson("/api/v1/admin/quizzes/{$quiz->id}/questions", [
            'type' => $type,
            'question_text' => 'Invalid configuration?',
            'points' => 1,
            'options' => $options,
        ])->assertUnprocessable()->assertJsonValidationErrors('options');

        $this->assertDatabaseCount('quiz_questions', 0);
        $this->assertDatabaseCount('quiz_options', 0);
    }

    public function test_invalid_update_preserves_question_and_options_transactionally(): void
    {
        $this->authenticate();
        $quiz = Quiz::factory()->create();
        $question = QuizQuestion::factory()->for($quiz)->create(['question_text' => 'Original']);
        $correct = QuizOption::factory()->for($question, 'question')->create(['answer_text' => 'Correct', 'is_correct' => true]);
        QuizOption::factory()->for($question, 'question')->create(['answer_text' => 'Wrong', 'is_correct' => false, 'sort_order' => 1]);

        $this->putJson("/api/v1/admin/quizzes/{$quiz->id}/questions/{$question->id}", [
            'type' => QuizQuestionType::SingleChoice->value,
            'question_text' => 'Changed',
            'points' => 2,
            'options' => [
                ['answer_text' => 'A', 'is_correct' => false],
                ['answer_text' => 'B', 'is_correct' => false],
            ],
        ])->assertUnprocessable()->assertJsonValidationErrors('options');

        $this->assertSame('Original', $question->fresh()->question_text);
        $this->assertSame('Correct', $correct->fresh()->answer_text);
        $this->assertDatabaseCount('quiz_options', 2);
    }

    public function test_question_reorder_is_zero_based_and_requires_exact_owned_ids(): void
    {
        $this->authenticate();
        $quiz = Quiz::factory()->create();
        $first = QuizQuestion::factory()->for($quiz)->create(['sort_order' => 0]);
        $second = QuizQuestion::factory()->for($quiz)->create(['sort_order' => 1]);

        $this->postJson("/api/v1/admin/quizzes/{$quiz->id}/questions/reorder", [
            'ids' => [$second->id, $first->id],
        ])->assertNoContent();

        $this->assertSame(1, $first->fresh()->sort_order);
        $this->assertSame(0, $second->fresh()->sort_order);

        $this->postJson("/api/v1/admin/quizzes/{$quiz->id}/questions/reorder", [
            'ids' => [$first->id],
        ])->assertUnprocessable()->assertJsonValidationErrors('ids');
    }

    public function test_cross_quiz_question_binding_returns_404(): void
    {
        $this->authenticate();
        $quiz = Quiz::factory()->create();
        $otherQuestion = QuizQuestion::factory()->create();

        $this->getJson("/api/v1/admin/quizzes/{$quiz->id}/questions/{$otherQuestion->id}")
            ->assertNotFound();
    }

    public function test_deleting_last_question_unpublishes_quiz(): void
    {
        $this->authenticate();
        $quiz = Quiz::factory()->published()->create();
        $question = QuizQuestion::factory()->for($quiz)->create();

        $this->deleteJson("/api/v1/admin/quizzes/{$quiz->id}/questions/{$question->id}")
            ->assertNoContent();

        $this->assertSame(QuizStatus::Draft, $quiz->fresh()->status);
        $this->assertSoftDeleted($question);
    }

    /** @return array<string, array{string, list<array{answer_text: string, is_correct: bool}>}> */
    public static function invalidConfigurations(): array
    {
        return [
            'single choice without correct answer' => [QuizQuestionType::SingleChoice->value, [
                ['answer_text' => 'A', 'is_correct' => false],
                ['answer_text' => 'B', 'is_correct' => false],
            ]],
            'single choice with two correct answers' => [QuizQuestionType::SingleChoice->value, [
                ['answer_text' => 'A', 'is_correct' => true],
                ['answer_text' => 'B', 'is_correct' => true],
            ]],
            'multiple choice without correct answer' => [QuizQuestionType::MultipleChoice->value, [
                ['answer_text' => 'A', 'is_correct' => false],
                ['answer_text' => 'B', 'is_correct' => false],
            ]],
            'true false with three options' => [QuizQuestionType::TrueFalse->value, [
                ['answer_text' => 'True', 'is_correct' => true],
                ['answer_text' => 'False', 'is_correct' => false],
                ['answer_text' => 'Maybe', 'is_correct' => false],
            ]],
            'true false with two correct answers' => [QuizQuestionType::TrueFalse->value, [
                ['answer_text' => 'True', 'is_correct' => true],
                ['answer_text' => 'False', 'is_correct' => true],
            ]],
        ];
    }

    private function authenticate(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $user = User::factory()->create();
        $user->assignRole(RoleName::ContentManager->value);
        Sanctum::actingAs($user);

        return $user;
    }
}
