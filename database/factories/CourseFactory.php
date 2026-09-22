<?php

namespace Database\Factories;

use App\CourseLevel;
use App\CourseStatus;
use App\Models\Category;
use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Course>
 */
class CourseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'category_id' => Category::factory(),
            'instructor_id' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'short_description' => fake()->sentence(),
            'description' => fake()->paragraphs(4, true),
            'level' => fake()->randomElement(CourseLevel::cases()),
            'language' => 'ar',
            'duration_minutes' => fake()->numberBetween(30, 1200),
            'access_duration_days' => fake()->optional()->numberBetween(30, 365),
            'price' => fake()->randomFloat(2, 0, 500),
            'certificate_enabled' => true,
            'discussion_enabled' => true,
            'status' => CourseStatus::Draft,
            'is_featured' => false,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CourseStatus::Published,
            'published_at' => now()->subDay(),
        ]);
    }
}
