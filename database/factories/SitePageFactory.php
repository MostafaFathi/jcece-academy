<?php

namespace Database\Factories;

use App\Models\SitePage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SitePage>
 */
class SitePageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->randomElement(SitePage::SLUGS),
            'draft_ar' => fake()->paragraph(),
            'draft_en' => fake()->paragraph(),
        ];
    }
}
