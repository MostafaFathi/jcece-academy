<?php

namespace Database\Factories;

use App\CourseReviewStatus;
use App\Models\Course;
use App\Models\CourseReview;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseReview>
 */
class CourseReviewFactory extends Factory
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
            'user_id' => User::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->optional()->sentence(5),
            'body' => fake()->optional()->paragraph(),
            'status' => CourseReviewStatus::Pending,
            'submitted_at' => now(),
            'published_at' => null,
            'moderated_by' => null,
            'moderated_at' => null,
            'moderation_reason' => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => [
            'status' => CourseReviewStatus::Published,
            'published_at' => now(),
            'moderated_by' => User::factory(),
            'moderated_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => CourseReviewStatus::Rejected,
            'moderated_by' => User::factory(),
            'moderated_at' => now(),
            'moderation_reason' => fake()->sentence(),
        ]);
    }

    public function hidden(): static
    {
        return $this->state(fn (): array => [
            'status' => CourseReviewStatus::Hidden,
            'published_at' => null,
            'moderated_by' => User::factory(),
            'moderated_at' => now(),
            'moderation_reason' => fake()->sentence(),
        ]);
    }
}
