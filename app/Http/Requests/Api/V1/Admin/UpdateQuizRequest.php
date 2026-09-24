<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Lesson;
use App\Models\Quiz;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateQuizRequest extends FormRequest
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
            'passing_score' => ['sometimes', 'required', 'numeric', 'min:0', 'max:100', 'decimal:0,2'],
            'time_limit_minutes' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:10080'],
            'max_attempts' => ['sometimes', 'nullable', 'integer', 'min:1', 'max:1000'],
            'shuffle_questions' => ['sometimes', 'boolean'],
            'shuffle_answers' => ['sometimes', 'boolean'],
            'show_results' => ['sometimes', 'boolean'],
            'show_correct_answers' => ['sometimes', 'boolean'],
            'available_from' => ['sometimes', 'nullable', 'date'],
            'available_until' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            /** @var Quiz $quiz */
            $quiz = $this->route('quiz');

            if (! $validator->errors()->has('lesson_id') && $this->filled('lesson_id')) {
                $lesson = Lesson::query()->with('section:id,course_id')->find($this->integer('lesson_id'));

                if ($lesson !== null && $lesson->section->course_id !== $quiz->course_id) {
                    $validator->errors()->add('lesson_id', 'The lesson must belong to the selected course.');
                }
            }

            if ($validator->errors()->hasAny(['available_from', 'available_until'])) {
                return;
            }

            $from = $this->exists('available_from') ? $this->input('available_from') : $quiz->available_from;
            $until = $this->exists('available_until') ? $this->input('available_until') : $quiz->available_until;

            if ($from !== null && $until !== null && CarbonImmutable::parse($until)->lte(CarbonImmutable::parse($from))) {
                $validator->errors()->add('available_until', 'The available until field must be after available from.');
            }
        }];
    }
}
