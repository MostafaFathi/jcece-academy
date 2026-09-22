<?php

namespace Database\Factories;

use App\LessonProgressStatus;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonProgress>
 */
class LessonProgressFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'enrollment_id' => Enrollment::factory(),
            'lesson_id' => Lesson::factory(),
            'status' => LessonProgressStatus::InProgress,
            'watched_seconds' => fake()->numberBetween(1, 300),
            'last_position_seconds' => fake()->numberBetween(0, 300),
            'started_at' => now()->subMinutes(10),
            'completed_at' => null,
        ];
    }

    public function notStarted(): static
    {
        return $this->state(fn (): array => [
            'status' => LessonProgressStatus::NotStarted,
            'watched_seconds' => 0,
            'last_position_seconds' => 0,
            'started_at' => null,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => LessonProgressStatus::Completed,
            'completed_at' => now(),
        ]);
    }
}
