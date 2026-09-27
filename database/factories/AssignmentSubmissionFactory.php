<?php

namespace Database\Factories;

use App\AssignmentSubmissionStatus;
use App\AssignmentSubmissionType;
use App\Models\Assignment;
use App\Models\AssignmentSubmission;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentSubmission>
 */
class AssignmentSubmissionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_id' => Assignment::factory(),
            'enrollment_id' => Enrollment::factory(),
            'user_id' => User::factory(),
            'attempt_number' => 1,
            'status' => AssignmentSubmissionStatus::Draft,
            'active_key' => null,
            'text_answer' => fake()->paragraph(),
            'submitted_at' => null,
            'is_late' => false,
            'assignment_title' => fake()->sentence(4),
            'assignment_instructions' => fake()->optional()->paragraph(),
            'maximum_score' => '100.00',
            'passing_score' => '60.00',
            'submission_type' => AssignmentSubmissionType::Text,
            'due_at' => null,
            'allow_late_submissions' => false,
            'score' => null,
            'passed' => null,
            'feedback' => null,
            'graded_by' => null,
            'graded_at' => null,
        ];
    }

    public function submitted(): static
    {
        return $this->state(fn (): array => [
            'status' => AssignmentSubmissionStatus::Submitted,
            'active_key' => null,
            'submitted_at' => now(),
        ]);
    }
}
