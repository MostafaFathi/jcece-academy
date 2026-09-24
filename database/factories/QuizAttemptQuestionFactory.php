<?php

namespace Database\Factories;

use App\Models\QuizAttempt;
use App\Models\QuizAttemptQuestion;
use App\QuizQuestionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizAttemptQuestion>
 */
class QuizAttemptQuestionFactory extends Factory
{
    protected $model = QuizAttemptQuestion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_attempt_id' => QuizAttempt::factory(),
            'source_question_id' => null,
            'type' => QuizQuestionType::SingleChoice,
            'question_text' => fake()->sentence().'?',
            'explanation' => fake()->optional()->paragraph(),
            'points' => 1,
            'sort_order' => 0,
        ];
    }
}
