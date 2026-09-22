<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseRequiredTool;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseRequiredTool>
 */
class CourseRequiredToolFactory extends Factory
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
            'tool' => fake()->word(),
            'sort_order' => 0,
        ];
    }
}
