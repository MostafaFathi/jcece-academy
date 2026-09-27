<?php

namespace Database\Factories;

use App\Models\AssignmentSubmission;
use App\Models\AssignmentSubmissionFile;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssignmentSubmissionFile>
 */
class AssignmentSubmissionFileFactory extends Factory
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
            'original_filename' => 'submission.pdf',
            'storage_disk' => 'local',
            'storage_path' => 'assignment-submissions/submission.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 1024,
        ];
    }
}
