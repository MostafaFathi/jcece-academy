<?php

namespace App\Http\Requests\Api\V1\Me;

use App\LessonType;
use App\Models\Lesson;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateLessonProgressRequest extends FormRequest
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
            'watched_seconds' => ['sometimes', 'required_without:last_position_seconds', 'integer', 'min:0'],
            'last_position_seconds' => ['sometimes', 'required_without:watched_seconds', 'integer', 'min:0'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('last_position_seconds') || ! $this->has('last_position_seconds')) {
                    return;
                }

                /** @var Lesson $lesson */
                $lesson = $this->route('lesson');

                if (
                    $lesson->type === LessonType::Video
                    && $lesson->duration_seconds !== null
                    && $this->integer('last_position_seconds') > $lesson->duration_seconds
                ) {
                    $validator->errors()->add('last_position_seconds', 'The last position may not exceed the lesson duration.');
                }
            },
        ];
    }
}
