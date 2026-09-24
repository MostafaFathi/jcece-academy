<?php

namespace Tests\Feature\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\EnrollmentAccessGrant;
use App\Models\Quiz;
use App\Models\QuizOption;
use App\Models\QuizQuestion;
use App\Models\User;
use App\QuizQuestionType;
use App\Services\QuizAttemptService;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class QuizAttemptServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_exact_multiple_choice_set_receives_full_credit(): void
    {
        $user = User::factory()->create();
        $course = Course::factory()->published()->create();
        $enrollment = Enrollment::factory()->for($user)->for($course)->create();
        EnrollmentAccessGrant::factory()->lifetime()->for($enrollment)->create();
        $quiz = Quiz::factory()->published()->for($course)->create(['passing_score' => 100]);
        $question = QuizQuestion::factory()->for($quiz)->create([
            'type' => QuizQuestionType::MultipleChoice,
            'points' => 2,
        ]);
        QuizOption::factory()->for($question, 'question')->create(['is_correct' => true, 'sort_order' => 0]);
        QuizOption::factory()->for($question, 'question')->create(['is_correct' => true, 'sort_order' => 1]);
        QuizOption::factory()->for($question, 'question')->create(['is_correct' => false, 'sort_order' => 2]);
        $service = app(QuizAttemptService::class);
        $attempt = $service->start($user, $quiz->load('course'));
        $snapshot = $attempt->questions->sole();
        $correctIds = $snapshot->options->where('is_correct', true)->modelKeys();

        $service->saveAnswers($user, $attempt, [[
            'question_id' => $snapshot->id,
            'option_ids' => $correctIds,
        ]]);
        $submitted = $service->submit($user, $attempt);

        $this->assertSame('2.00', $submitted->score);
        $this->assertSame('100.00', $submitted->percentage);
        $this->assertTrue($submitted->passed);
    }
}
