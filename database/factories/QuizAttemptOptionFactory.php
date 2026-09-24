<?php

namespace Database\Factories;

use App\Models\QuizAttemptOption;
use App\Models\QuizAttemptQuestion;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizAttemptOption>
 */
class QuizAttemptOptionFactory extends Factory
{
    protected $model = QuizAttemptOption::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_attempt_question_id' => QuizAttemptQuestion::factory(),
            'source_option_id' => null,
            'answer_text' => fake()->sentence(),
            'is_correct' => false,
            'sort_order' => 0,
        ];
    }
}
