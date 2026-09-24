<?php

namespace App\Services;

use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\QuizAttemptQuestion;
use App\Models\User;
use App\QuizAttemptStatus;
use App\QuizQuestionType;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class QuizAttemptService
{
    public function __construct(private QuizAccessService $access) {}

    public function start(User $user, Quiz $quiz): QuizAttempt
    {
        $enrollment = $this->access->requireAvailable($user, $quiz);

        return DB::transaction(function () use ($user, $quiz, $enrollment): QuizAttempt {
            $lockedQuiz = Quiz::query()->lockForUpdate()->findOrFail($quiz->id);
            $this->access->requireAvailable($user, $lockedQuiz);
            $activeAttempt = QuizAttempt::query()
                ->whereBelongsTo($lockedQuiz)
                ->whereBelongsTo($user)
                ->where('status', QuizAttemptStatus::InProgress)
                ->lockForUpdate()
                ->first();

            if ($activeAttempt !== null && ! $this->isExpired($activeAttempt)) {
                return $this->loadAttempt($activeAttempt);
            }

            if ($activeAttempt !== null) {
                $activeAttempt->update([
                    'status' => QuizAttemptStatus::Expired,
                    'active_key' => null,
                ]);
            }

            $attemptCount = QuizAttempt::query()
                ->whereBelongsTo($lockedQuiz)
                ->whereBelongsTo($user)
                ->count();

            if ($lockedQuiz->max_attempts !== null && $attemptCount >= $lockedQuiz->max_attempts) {
                throw ValidationException::withMessages(['quiz' => 'The maximum number of attempts has been reached.']);
            }

            $questions = $lockedQuiz->questions()->with('options')->lockForUpdate()->get();

            if ($questions->isEmpty()) {
                throw ValidationException::withMessages(['quiz' => 'This quiz has no questions available for an attempt.']);
            }

            if ($lockedQuiz->shuffle_questions) {
                $questions = $questions->shuffle()->values();
            }

            $maximumScore = $questions->reduce(
                fn (BigDecimal $total, $question): BigDecimal => $total->plus($question->points),
                BigDecimal::zero()->toScale(2),
            );
            $startedAt = now();
            $attempt = $lockedQuiz->attempts()->create([
                'enrollment_id' => $enrollment->id,
                'user_id' => $user->id,
                'attempt_number' => $attemptCount + 1,
                'status' => QuizAttemptStatus::InProgress,
                'active_key' => "quiz:{$lockedQuiz->id}:user:{$user->id}",
                'passing_score' => $lockedQuiz->passing_score,
                'show_results' => $lockedQuiz->show_results,
                'show_correct_answers' => $lockedQuiz->show_correct_answers,
                'started_at' => $startedAt,
                'expires_at' => $lockedQuiz->time_limit_minutes === null
                    ? null
                    : $startedAt->clone()->addMinutes($lockedQuiz->time_limit_minutes),
                'maximum_score' => (string) $maximumScore,
            ]);

            foreach ($questions as $questionSortOrder => $question) {
                $attemptQuestion = $attempt->questions()->create([
                    'source_question_id' => $question->id,
                    'type' => $question->type,
                    'question_text' => $question->question_text,
                    'explanation' => $question->explanation,
                    'points' => $question->points,
                    'sort_order' => $questionSortOrder,
                ]);
                $options = $lockedQuiz->shuffle_answers
                    ? $question->options->shuffle()->values()
                    : $question->options;

                foreach ($options as $optionSortOrder => $option) {
                    $attemptQuestion->options()->create([
                        'source_option_id' => $option->id,
                        'answer_text' => $option->answer_text,
                        'is_correct' => $option->is_correct,
                        'sort_order' => $optionSortOrder,
                    ]);
                }
            }

            return $this->loadAttempt($attempt);
        }, 3);
    }

    /** @param list<array{question_id: int, option_ids: list<int>}> $answers */
    public function saveAnswers(User $user, QuizAttempt $attempt, array $answers): QuizAttempt
    {
        $expired = false;

        $result = DB::transaction(function () use ($user, $attempt, $answers, &$expired): QuizAttempt {
            $lockedAttempt = QuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $this->authorizeOwner($user, $lockedAttempt);

            if ($lockedAttempt->status !== QuizAttemptStatus::InProgress) {
                throw ValidationException::withMessages(['attempt' => 'Answers may only be changed while an attempt is in progress.']);
            }

            $this->access->requireAvailable($user, $lockedAttempt->quiz()->with('course')->firstOrFail());

            if ($this->isExpired($lockedAttempt)) {
                $lockedAttempt->update(['status' => QuizAttemptStatus::Expired, 'active_key' => null]);
                $expired = true;

                return $lockedAttempt;
            }

            $questions = $lockedAttempt->questions()->with('options')->lockForUpdate()->get()->keyBy('id');

            foreach ($answers as $answer) {
                /** @var QuizAttemptQuestion|null $question */
                $question = $questions->get($answer['question_id']);

                if ($question === null) {
                    throw ValidationException::withMessages(['answers' => 'Every answer must reference a question from this attempt.']);
                }

                $selectedIds = collect($answer['option_ids'])->map(fn (mixed $id): int => (int) $id)->unique()->sort()->values();
                $optionIds = $question->options->modelKeys();

                if ($selectedIds->diff($optionIds)->isNotEmpty()) {
                    throw ValidationException::withMessages(['answers' => 'Every selected option must belong to its attempt question.']);
                }

                if (in_array($question->type, [QuizQuestionType::SingleChoice, QuizQuestionType::TrueFalse], true) && $selectedIds->count() > 1) {
                    throw ValidationException::withMessages(['answers' => 'This question accepts at most one selected option.']);
                }

                $lockedAttempt->answers()->updateOrCreate(
                    ['quiz_attempt_question_id' => $question->id],
                    ['selected_option_ids' => $selectedIds->all(), 'earned_points' => null],
                );
            }

            return $lockedAttempt;
        });

        if ($expired) {
            throw ValidationException::withMessages(['attempt' => 'The time limit for this attempt has expired.']);
        }

        return $this->loadAttempt($result);
    }

    public function submit(User $user, QuizAttempt $attempt): QuizAttempt
    {
        $expired = false;

        $result = DB::transaction(function () use ($user, $attempt, &$expired): QuizAttempt {
            $lockedAttempt = QuizAttempt::query()->lockForUpdate()->findOrFail($attempt->id);
            $this->authorizeOwner($user, $lockedAttempt);

            if ($lockedAttempt->status === QuizAttemptStatus::Submitted) {
                return $lockedAttempt;
            }

            if ($lockedAttempt->status === QuizAttemptStatus::Expired || $this->isExpired($lockedAttempt)) {
                $lockedAttempt->update(['status' => QuizAttemptStatus::Expired, 'active_key' => null]);
                $expired = true;

                return $lockedAttempt;
            }

            $this->access->requireAvailable($user, $lockedAttempt->quiz()->with('course')->firstOrFail());

            $questions = $lockedAttempt->questions()->with(['options', 'answer'])->lockForUpdate()->get();
            $score = BigDecimal::zero()->toScale(2);

            foreach ($questions as $question) {
                $answer = $question->answer()->firstOrCreate(
                    ['quiz_attempt_id' => $lockedAttempt->id],
                    ['selected_option_ids' => []],
                );
                $correctIds = $question->options->where('is_correct', true)->modelKeys();
                sort($correctIds);
                $selectedIds = array_map('intval', $answer->selected_option_ids);
                sort($selectedIds);
                $earnedPoints = $correctIds === $selectedIds ? $question->points : '0.00';
                $answer->update(['earned_points' => $earnedPoints]);
                $score = $score->plus($earnedPoints);
            }

            $maximumScore = BigDecimal::of($lockedAttempt->maximum_score);
            $percentage = $score->multipliedBy(100)->dividedBy($maximumScore, 2, RoundingMode::HalfUp);
            $passed = $percentage->compareTo(BigDecimal::of($lockedAttempt->passing_score)) >= 0;
            $lockedAttempt->update([
                'status' => QuizAttemptStatus::Submitted,
                'active_key' => null,
                'submitted_at' => now(),
                'score' => (string) $score,
                'percentage' => (string) $percentage,
                'passed' => $passed,
            ]);

            return $lockedAttempt;
        });

        if ($expired) {
            throw ValidationException::withMessages(['attempt' => 'The time limit for this attempt has expired.']);
        }

        return $this->loadAttempt($result);
    }

    public function refreshState(User $user, QuizAttempt $attempt): QuizAttempt
    {
        $this->authorizeOwner($user, $attempt);

        if ($attempt->status === QuizAttemptStatus::InProgress) {
            $this->access->requireAvailable($user, $attempt->quiz()->with('course')->firstOrFail());
        }

        if ($attempt->status === QuizAttemptStatus::InProgress && $this->isExpired($attempt)) {
            DB::transaction(function () use ($attempt): void {
                QuizAttempt::query()
                    ->whereKey($attempt->id)
                    ->where('status', QuizAttemptStatus::InProgress)
                    ->lockForUpdate()
                    ->update(['status' => QuizAttemptStatus::Expired, 'active_key' => null]);
            });
        }

        return $this->loadAttempt($attempt->refresh());
    }

    private function isExpired(QuizAttempt $attempt): bool
    {
        return $attempt->expires_at !== null && $attempt->expires_at->lte(now());
    }

    /** @throws AuthorizationException */
    private function authorizeOwner(User $user, QuizAttempt $attempt): void
    {
        if ($attempt->user_id !== $user->id) {
            throw new AuthorizationException('You do not own this quiz attempt.');
        }
    }

    private function loadAttempt(QuizAttempt $attempt): QuizAttempt
    {
        return $attempt->refresh()->load(['quiz', 'questions.options', 'questions.answer']);
    }
}
