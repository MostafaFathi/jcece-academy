<?php

namespace App\Http\Requests\Api\V1\Me;

use App\Models\QuizAttempt;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveQuizAnswersRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        /** @var QuizAttempt $attempt */
        $attempt = $this->route('attempt');

        return $attempt->user_id === $this->user()?->id;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'answers' => ['required', 'array', 'list', 'min:1'],
            'answers.*.question_id' => ['required', 'integer', 'distinct'],
            'answers.*.option_ids' => ['required', 'array', 'list'],
            'answers.*.option_ids.*' => ['integer', 'distinct'],
        ];
    }
}
