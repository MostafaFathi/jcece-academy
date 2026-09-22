<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseRequirement;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseRequirement>
 */
class CourseRequirementFactory extends Factory
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
            'requirement' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }
}
