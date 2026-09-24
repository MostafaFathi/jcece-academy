<?php

namespace Database\Factories;

use App\Models\Quiz;
use App\Models\QuizQuestion;
use App\QuizQuestionType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QuizQuestion>
 */
class QuizQuestionFactory extends Factory
{
    protected $model = QuizQuestion::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'quiz_id' => Quiz::factory(),
            'type' => QuizQuestionType::SingleChoice,
            'question_text' => fake()->sentence().'?',
            'explanation' => fake()->optional()->paragraph(),
            'points' => 1,
            'sort_order' => 0,
        ];
    }
}
