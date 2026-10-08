<?php

namespace Database\Factories;

use App\Models\SiteFaq;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SiteFaq>
 */
class SiteFaqFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_ar' => fake()->sentence().'؟',
            'answer_ar' => fake()->sentence(),
            'question_en' => fake()->sentence().'?',
            'answer_en' => fake()->sentence(),
            'sort_order' => 0,
            'is_active' => false,
        ];
    }
}
