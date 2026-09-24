<?php

namespace Tests\Feature\Api\V1\Me;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use App\QuizAttemptStatus;
use App\QuizQuestionType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class QuizAttemptApiTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_start_creates_immutable_snapshot_with_server_expiration_without_answer_key_leakage(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, $quiz] = $this->createAccessibleQuiz(['time_limit_minutes' => 30]);
        Sanctum::actingAs($user);

        $response = $this->postJson("/api/v1/me/quizzes/{$quiz->id}/attempts")
            ->assertCreated()
            ->assertJsonPath('data.status', QuizAttemptStatus::InProgress->value)
            ->assertJsonPath('data.expires_at', '2026-10-01T12:30:00.000000Z')
            ->assertJsonCount(3, 'data.questions')
            ->assertJsonMissingPath('data.questions.0.correct_option_ids')
            ->assertJsonMissingPath('data.questions.0.explanation');

        $this->assertStringNotContainsString('is_correct', $response->getContent());
        $this->assertStringNotContainsString('Secret explanation', $response->getContent());
        $this->assertDatabaseCount('quiz_attempts', 1);
        $this->assertDatabaseCount('quiz_attempt_questions', 3);
        $this->assertDatabaseCount('quiz_attempt_options', 7);
        $this->assertSame('6.00', QuizAttempt::query()->sole()->maximum_score);
    }

    public function test_start_returns_existing_active_attempt_and_enforces_max_attempts(): void
    {
        [$user, $quiz] = $this->createAccessibleQuiz(['max_attempts' => 1]);
        Sanctum::actingAs($user);

        $firstId = $this->postJson("/api/v1/me/quizzes/{$quiz->id}/attempts")
            ->assertCreated()->json('data.id');
        $this->postJson("/api/v1/me/quizzes/{$quiz->id}/attempts")
            ->assertCreated()->assertJsonPath('data.id', $firstId);
        $this->postJson("/api/v1/me/quiz-attempts/{$firstId}/submit")->assertOk();

        $this->postJson("/api/v1/me/quizzes/{$quiz->id}/attempts")
            ->assertUnprocessable()->assertJsonValidationErrors('quiz');

        $this->assertDatabaseCount('quiz_attempts', 1);
    }

    public function test_saved_answers_are_graded_for_all_types_with_exact_set_multiple_choice_and_passing_boundary(): void
    {
        [$user, $quiz] = $this->createAccessibleQuiz(['passing_score' => 50]);
        Sanctum::actingAs($user);
        $attemptId = $this->postJson("/api/v1/me/quizzes/{$quiz->id}/attempts")->assertCreated()->json('data.id');
        $attempt = QuizAttempt::findOrFail($attemptId)->load('questions.options');
        $single = $attempt->questions->firstWhere('type', QuizQuestionType::SingleChoice);
        $multiple = $attempt->questions->firstWhere('type', QuizQuestionType::MultipleChoice);
        $trueFalse = $attempt->questions->firstWhere('type', QuizQuestionType::TrueFalse);

        $this->patchJson("/api/v1/me/quiz-attempts/{$attemptId}/answers", ['answers' => [
            ['question_id' => $single->id, 'option_ids' => $single->options->where('is_correct', true)->modelKeys()],
            ['question_id' => $multiple->id, 'option_ids' => $multiple->options->modelKeys()],
            ['question_id' => $trueFalse->id, 'option_ids' => $trueFalse->options->where('is_correct', true)->modelKeys()],
        ]])->assertOk();

        $this->postJson("/api/v1/me/quiz-attempts/{$attemptId}/submit")
            ->assertOk()
            ->assertJsonPath('data.score', '3.00')
            ->assertJsonPath('data.maximum_score', '6.00')
            ->assertJsonPath('data.percentage', '50.00')
            ->assertJsonPath('data.passed', true);

        $earned = $attempt->answers()->orderBy('quiz_attempt_question_id')->pluck('earned_points')->all();
        $this->assertSame(['2.00', '0.00', '1.00'], $earned);
    }

    public function test_repeated_submission_is_idempotent(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, $quiz] = $this->createAccessibleQuiz();
        Sanctum::actingAs($user);
        $attemptId = $this->postJson("/api/v1/me/quizzes/{$quiz->id}/attempts")->json('data.id');
        $this->postJson("/api/v1/me/quiz-attempts/{$attemptId}/submit")->assertOk();
        $attempt = QuizAttempt::findOrFail($attemptId);
        $submittedAt = $attempt->submitted_at?->toISOString();
        $score = $attempt->score;
        $this->travel(10)->minutes();

        $this->postJson("/api/v1/me/quiz-attempts/{$attemptId}/submit")->assertOk();

        $attempt->refresh();
        $this->assertSame($submittedAt, $attempt->submitted_at?->toISOString());
        $this->assertSame($score, $attempt->score);
        $this->assertDatabaseCount('quiz_attempt_answers', 3);

        $question = $attempt->questions()->with('options')->firstOrFail();
        $this->patchJson("/api/v1/me/quiz-attempts/{$attemptId}/answers", ['answers' => [[
            'question_id' => $question->id,
            'option_ids' => [$question->options->first()->id],
        ]]])->assertUnprocessable()->assertJsonValidationErrors('attempt');
    }

    public function test_server_time_limit_expires_attempt_and_rejects_late_answers(): void
    {
        $this->travelTo('2026-10-01 12:00:00');
        [$user, $quiz] = $this->createAccessibleQuiz(['time_limit_minutes' => 1]);
        Sanctum::actingAs($user);
        $attemptId = $this->postJson("/api/v1/me/quizzes/{$quiz->id}/attempts")->json('data.id');
        $attempt = QuizAttempt::findOrFail($attemptId)->load('questions.options');
        $question = $attempt->questions->first();
        $this->travel(2)->minutes();

        $this->patchJson("/api/v1/me/quiz-attempts/{$attemptId}/answers", ['answers' => [[
            'question_id' => $question->id,
            'option_ids' => [$question->options->first()->id],
        ]]])->assertUnprocessable()->assertJsonValidationErrors('attempt');

        $this->assertSame(QuizAttemptStatus::Expired, $attempt->fresh()->status);
        $this->assertNull($attempt->fresh()->active_key);
        $this->assertDatabaseCount('quiz_attempt_answers', 0);

        $secondAttemptId = $this->postJson("/api/v1/me/quizzes/{$quiz->id}/attempts")
            ->assertCreated()->json('data.id');
        $this->travel(2)->minutes();
        $this->postJson("/api/v1/me/quiz-attempts/{$secondAttemptId}/submit")
            ->assertUnprocessable()->assertJsonValidationErrors('attempt');
        $this->assertSame(QuizAttemptStatus::Expired, QuizAttempt::findOrFail($secondAttemptId)->status);
    }

    public function test_other_student_cannot_view_save_or_submit_attempt(): void
    {
        [$owner, $quiz] = $this->createAccessibleQuiz();
        Sanctum::actingAs($owner);
        $attemptId = $this->postJson("/api/v1/me/quizzes/{$quiz->id}/attempts")->json('data.id');
        $other = User::factory()->create();
        Sanctum::actingAs($other);

        $this->getJson("/api/v1/me/quiz-attempts/{$attemptId}")->assertForbidden();
        $this->patchJson("/api/v1/me/quiz-attempts/{$attemptId}/answers", ['answers' => []])->assertForbidden();
        $this->postJson("/api/v1/me/quiz-attempts/{$attemptId}/submit")->assertForbidden();
    }

    public function test_invalid_answer_returns_422_without_leaking_answer_key(): void
    {
        [$user, $quiz] = $this->createAccessibleQuiz();
        Sanctum::actingAs($user);
        $attemptId = $this->postJson("/api/v1/me/quizzes/{$quiz->id}/attempts")->json('data.id');
        $attemptQuestion = QuizAttempt::findOrFail($attemptId)->questions()->firstOrFail();
        $foreignOption = QuizOption::factory()->create(['is_correct' => true]);

        $response = $this->patchJson("/api/v1/me/quiz-attempts/{$attemptId}/answers", ['answers' => [[
            'question_id' => $attemptQuestion->id,
            'option_ids' => [$foreignOption->id],
        ]]])->assertUnprocessable()->assertJsonValidationErrors('answers');

        $this->assertStringNotContainsString('is_correct', $response->getContent());
        $this->assertStringNotContainsString('Secret explanation', $response->getContent());
        $this->assertDatabaseCount('quiz_attempt_answers', 0);
    }

    public function test_attempt_snapshot_and_grading_remain_immutable_after_quiz_edits(): void
    {
        [$user, $quiz, $sourceQuestions] = $this->createAccessibleQuiz(['passing_score' => 50]);
        Sanctum::actingAs($user);
        $attemptId = $this->postJson("/api/v1/me/quizzes/{$quiz->id}/attempts")->json('data.id');
        $attempt = QuizAttempt::findOrFail($attemptId)->load('questions.options');
        $snapshotQuestion = $attempt->questions->firstWhere('source_question_id', $sourceQuestions[0]->id);
        $snapshotCorrectId = $snapshotQuestion->options->firstWhere('is_correct', true)->id;
        $sourceQuestions[0]->update(['question_text' => 'Changed current question', 'points' => 100]);
        $sourceQuestions[0]->options()->update(['is_correct' => false]);
        $quiz->update(['passing_score' => 100]);

        $this->patchJson("/api/v1/me/quiz-attempts/{$attemptId}/answers", ['answers' => [[
            'question_id' => $snapshotQuestion->id,
            'option_ids' => [$snapshotCorrectId],
        ]]])->assertOk();
        $this->postJson("/api/v1/me/quiz-attempts/{$attemptId}/submit")->assertOk();

        $attempt->refresh();
        $this->assertSame('2.00', $attempt->score);
        $this->assertSame('6.00', $attempt->maximum_score);
        $this->assertSame('Original single choice?', $snapshotQuestion->fresh()->question_text);
        $this->assertSame('50.00', $attempt->passing_score);
    }

    public function test_result_and_correct_answer_visibility_are_independent(): void
    {
        [$user, $hiddenResultsQuiz] = $this->createAccessibleQuiz([
            'show_results' => false,
            'show_correct_answers' => true,
        ]);
        Sanctum::actingAs($user);
        $hiddenResultsAttempt = $this->postJson("/api/v1/me/quizzes/{$hiddenResultsQuiz->id}/attempts")->json('data.id');

        $this->postJson("/api/v1/me/quiz-attempts/{$hiddenResultsAttempt}/submit")
            ->assertOk()
            ->assertJsonMissingPath('data.score')
            ->assertJsonMissingPath('data.passed')
            ->assertJsonPath('data.questions.0.explanation', 'Secret explanation')
            ->assertJsonStructure(['data' => ['questions' => [['correct_option_ids']]]]);

        [$secondUser, $hiddenAnswersQuiz] = $this->createAccessibleQuiz([
            'show_results' => true,
            'show_correct_answers' => false,
        ]);
        Sanctum::actingAs($secondUser);
        $hiddenAnswersAttempt = $this->postJson("/api/v1/me/quizzes/{$hiddenAnswersQuiz->id}/attempts")->json('data.id');

        $response = $this->postJson("/api/v1/me/quiz-attempts/{$hiddenAnswersAttempt}/submit")
            ->assertOk()
            ->assertJsonPath('data.score', '0.00')
            ->assertJsonMissingPath('data.questions.0.correct_option_ids')
            ->assertJsonMissingPath('data.questions.0.explanation');
        $this->assertStringNotContainsString('is_correct', $response->getContent());
    }

    public function test_attempt_history_lists_only_owners_attempts(): void
    {
        [$user, $quiz] = $this->createAccessibleQuiz();
        Sanctum::actingAs($user);
        $ownAttempt = $this->postJson("/api/v1/me/quizzes/{$quiz->id}/attempts")->json('data.id');
        QuizAttempt::factory()->for($quiz)->create();

        $this->getJson("/api/v1/me/quizzes/{$quiz->id}/attempts")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $ownAttempt);
    }

    public function test_access_revocation_blocks_changes_to_active_attempt(): void
    {
        [$user, $quiz, , $enrollment] = $this->createAccessibleQuiz();
        Sanctum::actingAs($user);
        $attemptId = $this->postJson("/api/v1/me/quizzes/{$quiz->id}/attempts")->json('data.id');
        $enrollment->accessGrants()->delete();

        $this->postJson("/api/v1/me/quiz-attempts/{$attemptId}/submit")->assertForbidden();
        $this->assertSame(QuizAttemptStatus::InProgress, QuizAttempt::findOrFail($attemptId)->status);
    }

    /**
     * @param  array<string, mixed>  $quizAttributes
     * @return array{User, Quiz, list<QuizQuestion>, Enrollment}
     */
    private function createAccessibleQuiz(array $quizAttributes = []): array
    {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($user)->for($course)->create();
        EnrollmentAccessGrant::factory()->lifetime()->for($enrollment)->create();
        $quiz = Quiz::factory()->published()->for($course)->create($quizAttributes);

        $single = QuizQuestion::factory()->for($quiz)->create([
            'type' => QuizQuestionType::SingleChoice,
            'question_text' => 'Original single choice?',
            'explanation' => 'Secret explanation',
            'points' => 2,
            'sort_order' => 0,
        ]);
        QuizOption::factory()->for($single, 'question')->create(['answer_text' => 'A', 'is_correct' => true, 'sort_order' => 0]);
        QuizOption::factory()->for($single, 'question')->create(['answer_text' => 'B', 'is_correct' => false, 'sort_order' => 1]);

        $multiple = QuizQuestion::factory()->for($quiz)->create([
            'type' => QuizQuestionType::MultipleChoice,
            'question_text' => 'Multiple choice?',
            'points' => 3,
            'sort_order' => 1,
        ]);
        QuizOption::factory()->for($multiple, 'question')->create(['answer_text' => 'C', 'is_correct' => true, 'sort_order' => 0]);
        QuizOption::factory()->for($multiple, 'question')->create(['answer_text' => 'D', 'is_correct' => true, 'sort_order' => 1]);
        QuizOption::factory()->for($multiple, 'question')->create(['answer_text' => 'E', 'is_correct' => false, 'sort_order' => 2]);

        $trueFalse = QuizQuestion::factory()->for($quiz)->create([
            'type' => QuizQuestionType::TrueFalse,
            'question_text' => 'True or false?',
            'points' => 1,
            'sort_order' => 2,
        ]);
        QuizOption::factory()->for($trueFalse, 'question')->create(['answer_text' => 'True', 'is_correct' => true, 'sort_order' => 0]);
        QuizOption::factory()->for($trueFalse, 'question')->create(['answer_text' => 'False', 'is_correct' => false, 'sort_order' => 1]);

        return [$user, $quiz, [$single, $multiple, $trueFalse], $enrollment];
    }
}
