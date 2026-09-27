<?php

namespace Database\Factories;

use App\AssignmentStatus;
use App\AssignmentSubmissionType;
use App\Models\Assignment;
use App\Models\Course;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Assignment>
 */
class AssignmentFactory extends Factory
{
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
            'status' => AssignmentStatus::Draft,
            'submission_type' => AssignmentSubmissionType::Text,
            'maximum_score' => '100.00',
            'passing_score' => '60.00',
            'max_attempts' => null,
            'available_from' => null,
            'due_at' => null,
            'allow_late_submissions' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => AssignmentStatus::Published]);
    }
}
