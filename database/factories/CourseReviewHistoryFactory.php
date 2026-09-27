<?php

namespace Database\Factories;

use App\CourseReviewStatus;
use App\Models\CourseReview;
use App\Models\CourseReviewHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CourseReviewHistory>
 */
class CourseReviewHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'course_review_id' => CourseReview::factory(),
            'actor_id' => User::factory(),
            'actor_name_snapshot' => fake()->name(),
            'action' => 'submitted',
            'from_status' => null,
            'to_status' => CourseReviewStatus::Pending,
            'old_rating' => null,
            'new_rating' => fake()->numberBetween(1, 5),
            'old_title' => null,
            'new_title' => fake()->sentence(),
            'old_body' => null,
            'new_body' => fake()->paragraph(),
            'reason' => null,
            'created_at' => now(),
        ];
    }
}
