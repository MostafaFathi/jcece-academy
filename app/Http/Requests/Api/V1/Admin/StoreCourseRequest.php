<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\CourseLevel;
use App\CourseStatus;
use App\Models\User;
use App\RoleName;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCourseRequest extends FormRequest
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
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->where('is_active', true))],
            'instructor_id' => ['required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('courses', 'slug')],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'string', 'max:2048'],
            'promo_video_url' => ['nullable', 'url', 'max:2048'],
            'level' => ['required', Rule::enum(CourseLevel::class)],
            'language' => ['sometimes', 'string', 'max:10'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'access_duration_days' => ['nullable', 'integer', 'min:1'],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'compare_price' => ['nullable', 'numeric', 'gte:price', 'max:9999999999.99'],
            'discount_starts_at' => ['nullable', 'date'],
            'discount_ends_at' => ['nullable', 'date', 'after_or_equal:discount_starts_at'],
            'certificate_enabled' => ['sometimes', 'boolean'],
            'discussion_enabled' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::enum(CourseStatus::class)],
            'is_featured' => ['sometimes', 'boolean'],
            'published_at' => ['nullable', 'date'],
            'learning_outcomes' => ['sometimes', 'array'],
            'learning_outcomes.*' => ['required', 'string'],
            'requirements' => ['sometimes', 'array'],
            'requirements.*' => ['required', 'string'],
            'target_audiences' => ['sometimes', 'array'],
            'target_audiences.*' => ['required', 'string'],
            'required_tools' => ['sometimes', 'array'],
            'required_tools.*' => ['required', 'string'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('instructor_id')) {
                    return;
                }

                $instructor = User::find($this->integer('instructor_id'));

                if (! $instructor?->hasRole(RoleName::Instructor->value)) {
                    $validator->errors()->add('instructor_id', 'The selected user must have the instructor role.');
                }
            },
        ];
    }
}
