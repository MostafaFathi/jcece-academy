<?php

namespace Database\Factories;

use App\LessonType;
use App\Models\CourseSection;
use App\Models\Lesson;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Lesson>
 */
class LessonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->sentence(4);

        return [
            'course_section_id' => CourseSection::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numberBetween(1, 99999),
            'type' => LessonType::Text,
            'description' => fake()->optional()->sentence(),
            'content' => fake()->paragraphs(3, true),
            'duration_seconds' => fake()->numberBetween(60, 3600),
            'is_preview' => false,
            'is_published' => false,
            'sort_order' => 0,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => ['is_published' => true]);
    }

    public function preview(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_preview' => true,
            'is_published' => true,
        ]);
    }

    public function video(): static
    {
        return $this->state(fn (array $attributes): array => [
            'type' => LessonType::Video,
            'content' => null,
            'video_provider' => 'youtube',
            'video_id' => fake()->bothify('???????????'),
            'video_url' => 'https://www.youtube.com/watch?v='.fake()->bothify('???????????'),
        ]);
    }
}
