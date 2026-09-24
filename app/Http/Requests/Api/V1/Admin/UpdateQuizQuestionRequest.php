<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\QuizQuestionType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateQuizQuestionRequest extends FormRequest
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
            'type' => ['required', Rule::enum(QuizQuestionType::class)],
            'question_text' => ['required', 'string'],
            'explanation' => ['nullable', 'string'],
            'points' => ['required', 'numeric', 'min:0.01', 'max:999999.99', 'decimal:0,2'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'options' => ['required', 'array', 'list', 'min:2'],
            'options.*.answer_text' => ['required', 'string'],
            'options.*.is_correct' => ['required', 'boolean'],
        ];
    }
}
