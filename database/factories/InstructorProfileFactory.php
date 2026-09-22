<?php

namespace Database\Factories;

use App\Models\InstructorProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InstructorProfile>
 */
class InstructorProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'job_title' => fake()->jobTitle(),
            'short_bio' => fake()->paragraph(),
            'bio' => fake()->paragraphs(3, true),
            'years_experience' => fake()->numberBetween(1, 25),
            'specialties' => fake()->words(3),
            'linkedin_url' => fake()->optional()->url(),
            'website_url' => fake()->optional()->url(),
            'is_featured' => false,
        ];
    }
}
