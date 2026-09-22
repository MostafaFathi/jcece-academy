<?php

namespace Database\Factories;

use App\EnrollmentStatus;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Enrollment>
 */
class EnrollmentFactory extends Factory
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
            'course_id' => Course::factory(),
            'status' => EnrollmentStatus::Active,
            'enrolled_at' => now(),
            'completed_at' => null,
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => EnrollmentStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn (): array => ['status' => EnrollmentStatus::Suspended]);
    }
}
