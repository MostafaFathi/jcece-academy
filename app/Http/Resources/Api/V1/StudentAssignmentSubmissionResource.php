<?php

namespace App\Http\Resources\Api\V1;

use App\AssignmentSubmissionStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentAssignmentSubmissionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $hasFinalGrade = $this->status === AssignmentSubmissionStatus::Graded;

        return [
            'id' => $this->id,
            'assignment_id' => $this->assignment_id,
            'attempt_number' => $this->attempt_number,
            'status' => $this->status->value,
            'text_answer' => $this->text_answer,
            'submitted_at' => $this->submitted_at,
            'is_late' => $this->is_late,
            'assignment_title' => $this->assignment_title,
            'assignment_instructions' => $this->assignment_instructions,
            'maximum_score' => $this->maximum_score,
            'passing_score' => $this->passing_score,
            'submission_type' => $this->submission_type->value,
            'due_at' => $this->due_at,
            'allow_late_submissions' => $this->allow_late_submissions,
            'score' => $this->when($hasFinalGrade, $this->score),
            'passed' => $this->when($hasFinalGrade, $this->passed),
            'feedback' => $this->when(
                in_array($this->status, [AssignmentSubmissionStatus::Graded, AssignmentSubmissionStatus::RevisionRequested], true),
                $this->feedback,
            ),
            'graded_at' => $this->when($hasFinalGrade, $this->graded_at),
            'files' => AssignmentSubmissionFileResource::collection($this->whenLoaded('files')),
        ];
    }
}
