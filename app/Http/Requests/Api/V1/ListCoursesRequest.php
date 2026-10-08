<?php

namespace App\Http\Requests\Api\V1;

use App\CourseLevel;
use App\CourseStatus;
use App\CourseTrainingType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListCoursesRequest extends FormRequest
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
            'search' => ['sometimes', 'string', 'max:255'],
            'category' => ['sometimes', 'string', 'max:255'],
            'instructor' => ['sometimes', 'integer', 'exists:users,id'],
            'level' => ['sometimes', Rule::enum(CourseLevel::class)],
            'language' => ['sometimes', 'string', 'max:10'],
            'training_type' => ['sometimes', Rule::enum(CourseTrainingType::class)],
            'price_type' => ['sometimes', Rule::in(['free', 'paid'])],
            'rating_min' => ['sometimes', 'integer', 'between:1,5'],
            'status' => ['sometimes', Rule::enum(CourseStatus::class)],
            'sort' => ['sometimes', Rule::in(['latest', 'oldest', 'price_asc', 'price_desc', 'title', 'rating', 'bestseller'])],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
