<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCourseCertificateRequirementsRequest extends FormRequest
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
        $courseId = $this->route('course')->id;

        return [
            'certificate_enabled' => ['required', 'boolean'],
            'required_lesson_percentage' => ['required', 'numeric', 'between:0,100', 'decimal:0,2'],
            'final_exam_required' => ['required', 'boolean'],
            'final_exam_quiz_id' => [Rule::requiredIf($this->boolean('final_exam_required')), 'nullable', 'integer', Rule::exists('quizzes', 'id')->where('course_id', $courseId)->whereNull('deleted_at')],
            'final_exam_passing_percentage' => [Rule::requiredIf($this->boolean('final_exam_required')), 'nullable', 'numeric', 'between:0,100', 'decimal:0,2'],
            'required_assignment_ids' => ['present', 'array'],
            'required_assignment_ids.*' => ['integer', 'distinct', Rule::exists('assignments', 'id')->where('course_id', $courseId)->whereNull('deleted_at')],
            'admin_approval_required' => ['required', 'boolean'],
        ];
    }
}
