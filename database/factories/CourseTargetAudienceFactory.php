<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseTargetAudience;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseTargetAudience>
 */
class CourseTargetAudienceFactory extends Factory
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
            'audience' => fake()->sentence(),
            'sort_order' => 0,
        ];
    }
}
