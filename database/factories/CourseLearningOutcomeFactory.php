<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseLearningOutcome;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseLearningOutcome>
 */
class CourseLearningOutcomeFactory extends Factory
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
            'outcome' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }
}
