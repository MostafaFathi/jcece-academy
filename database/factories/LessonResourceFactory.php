<?php

namespace Database\Factories;

use App\Models\Lesson;
use App\Models\LessonResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LessonResource>
 */
class LessonResourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lesson_id' => Lesson::factory(),
            'title' => fake()->sentence(3),
            'type' => 'pdf',
            'file_path' => 'lesson-resources/'.fake()->uuid().'.pdf',
            'external_url' => null,
            'is_downloadable' => true,
            'sort_order' => 0,
        ];
    }
}
