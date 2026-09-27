<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\AssignmentSubmissionType;
use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreAssignmentRequest extends FormRequest
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
            'lesson_id' => ['nullable', 'integer', 'exists:lessons,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'instructions' => ['nullable', 'string'],
            'submission_type' => ['required', Rule::enum(AssignmentSubmissionType::class)],
            'maximum_score' => ['required', 'numeric', 'gt:0', 'max:99999999.99', 'decimal:0,2'],
            'passing_score' => ['nullable', 'numeric', 'min:0', 'lte:maximum_score', 'decimal:0,2'],
            'max_attempts' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'available_from' => ['nullable', 'date'],
            'due_at' => ['nullable', 'date', 'after:available_from'],
            'allow_late_submissions' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('lesson_id') || ! $this->filled('lesson_id')) {
                return;
            }

            /** @var Course $course */
            $course = $this->route('course');
            $lesson = Lesson::query()->with('section:id,course_id')->find($this->integer('lesson_id'));

            if ($lesson !== null && $lesson->section->course_id !== $course->id) {
                $validator->errors()->add('lesson_id', 'The lesson must belong to the selected course.');
            }
        }];
    }
}
