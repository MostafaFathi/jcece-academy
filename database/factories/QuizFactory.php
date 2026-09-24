<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\Quiz;
use App\QuizStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Quiz>
 */
class QuizFactory extends Factory
{
    protected $model = Quiz::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_id' => Course::factory(),
            'lesson_id' => null,
            'title' => fake()->sentence(4),
            'description' => fake()->optional()->paragraph(),
            'instructions' => fake()->optional()->paragraph(),
            'status' => QuizStatus::Draft,
            'passing_score' => 60,
            'time_limit_minutes' => null,
            'max_attempts' => null,
            'shuffle_questions' => false,
            'shuffle_answers' => false,
            'show_results' => true,
            'show_correct_answers' => false,
            'available_from' => null,
            'available_until' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => QuizStatus::Published]);
    }
}
