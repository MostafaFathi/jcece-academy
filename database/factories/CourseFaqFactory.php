<?php

namespace Database\Factories;

use App\Models\Course;
use App\Models\CourseFaq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseFaq>
 */
class CourseFaqFactory extends Factory
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
            'question_ar' => fake()->sentence().'؟',
            'answer_ar' => fake()->sentence(),
            'question_en' => fake()->sentence().'?',
            'answer_en' => fake()->sentence(),
            'is_active' => false,
            'sort_order' => 0,
        ];
    }
}
