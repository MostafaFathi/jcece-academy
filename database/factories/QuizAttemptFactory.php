<?php

namespace Database\Factories;

use App\Models\Enrollment;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\User;
use App\QuizAttemptStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizAttempt>
 */
class QuizAttemptFactory extends Factory
{
    protected $model = QuizAttempt::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'enrollment_id' => Enrollment::factory(),
            'user_id' => User::factory(),
            'attempt_number' => 1,
            'status' => QuizAttemptStatus::InProgress,
            'active_key' => fake()->uuid(),
            'passing_score' => 60,
            'show_results' => true,
            'show_correct_answers' => false,
            'started_at' => now(),
            'expires_at' => null,
            'submitted_at' => null,
            'score' => null,
            'maximum_score' => 1,
            'percentage' => null,
            'passed' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => QuizAttemptStatus::Submitted,
            'active_key' => null,
            'submitted_at' => now(),
            'score' => 1,
            'percentage' => 100,
            'passed' => true,
        ]);
    }
}
