<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\AssignmentSubmissionType;
use App\Models\Assignment;
use App\Models\Lesson;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateAssignmentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'lesson_id' => ['sometimes', 'nullable', 'integer', 'exists:lessons,id'],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'instructions' => ['sometimes', 'nullable', 'string'],
            'submission_type' => ['sometimes', 'required', Rule::enum(AssignmentSubmissionType::class)],
            'maximum_score' => ['sometimes', 'required', 'numeric', 'gt:0', 'max:99999999.99', 'decimal:0,2'],
            'passing_score' => ['sometimes', 'nullable', 'numeric', 'min:0', 'decimal:0,2'],
            'max_attempts' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'available_from' => ['sometimes', 'nullable', 'date'],
            'due_at' => ['sometimes', 'nullable', 'date'],
            'allow_late_submissions' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            /** @var Assignment $assignment */
            $assignment = $this->route('assignment');
            $lessonId = $this->exists('lesson_id') ? $this->input('lesson_id') : $assignment->lesson_id;
            $maximumScore = $this->input('maximum_score', $assignment->maximum_score);
            $passingScore = $this->exists('passing_score') ? $this->input('passing_score') : $assignment->passing_score;
            $availableFrom = $this->exists('available_from') ? $this->date('available_from') : $assignment->available_from;
            $dueAt = $this->exists('due_at') ? $this->date('due_at') : $assignment->due_at;

            if ($lessonId !== null) {
                $lesson = Lesson::query()->with('section:id,course_id')->find($lessonId);

                if ($lesson !== null && $lesson->section->course_id !== $assignment->course_id) {
                    $validator->errors()->add('lesson_id', 'The lesson must belong to the selected course.');
                }
            }

            if ($passingScore !== null && (float) $passingScore > (float) $maximumScore) {
                $validator->errors()->add('passing_score', 'The passing score must not exceed the maximum score.');
            }

            if ($availableFrom !== null && $dueAt !== null && $dueAt->lte($availableFrom)) {
                $validator->errors()->add('due_at', 'The due date must be after the availability date.');
            }
        }];
    }
}
