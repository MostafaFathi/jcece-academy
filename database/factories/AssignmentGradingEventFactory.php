<?php

namespace Database\Factories;

use App\AssignmentGradingAction;
use App\Models\AssignmentGradingEvent;
use App\Models\AssignmentSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentGradingEvent>
 */
class AssignmentGradingEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'assignment_submission_id' => AssignmentSubmission::factory(),
            'reviewer_id' => User::factory(),
            'action' => AssignmentGradingAction::Graded,
            'previous_score' => null,
            'previous_passed' => null,
            'previous_feedback' => null,
            'score' => '80.00',
            'passed' => true,
            'feedback' => fake()->sentence(),
            'reason' => null,
        ];
    }
}
