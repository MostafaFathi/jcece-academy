<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\CourseLevel;
use App\CourseStatus;
use App\CourseTrainingType;
use App\Models\Course;
use App\Models\User;
use App\RoleName;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCourseRequest extends FormRequest
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
        /** @var Course $course */
        $course = $this->route('course');

        return [
            'category_id' => ['sometimes', 'required', 'integer', Rule::exists('categories', 'id')->where(fn ($query) => $query->whereNull('deleted_at')->where('is_active', true))],
            'instructor_id' => ['sometimes', 'required', 'integer', Rule::exists('users', 'id')->whereNull('deleted_at')],
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash', Rule::unique('courses', 'slug')->ignore($course)],
            'short_description' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'string', 'max:2048'],
            'promo_video_url' => ['nullable', 'url:http,https', 'max:2048'],
            'level' => ['sometimes', 'required', Rule::enum(CourseLevel::class)],
            'training_type' => ['sometimes', Rule::enum(CourseTrainingType::class)],
            'language' => ['sometimes', 'string', 'max:10'],
            'duration_minutes' => ['nullable', 'integer', 'min:1'],
            'access_duration_days' => ['nullable', 'integer', 'min:1'],
            'price' => ['sometimes', 'numeric', 'min:0', 'max:9999999999.99'],
            'compare_price' => ['nullable', 'numeric', 'gte:price', 'max:9999999999.99'],
            'promotional_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
            'discount_starts_at' => ['nullable', 'date'],
            'discount_ends_at' => ['nullable', 'date'],
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
                if (! $this->has('instructor_id') || $validator->errors()->has('instructor_id')) {
                    return;
                }

                $instructor = User::find($this->integer('instructor_id'));

                if (! $instructor?->hasRole(RoleName::Instructor->value)) {
                    $validator->errors()->add('instructor_id', 'The selected user must have the instructor role.');
                }
            },
            function (Validator $validator): void {
                /** @var Course $course */
                $course = $this->route('course');
                if ($validator->errors()->hasAny(['price', 'promotional_price', 'discount_starts_at', 'discount_ends_at'])) {
                    return;
                }
                $price = (string) $this->input('price', $course->price);
                $promotion = $this->exists('promotional_price') ? $this->input('promotional_price') : $course->promotional_price;
                $start = $this->exists('discount_starts_at') ? $this->input('discount_starts_at') : $course->discount_starts_at;
                $end = $this->exists('discount_ends_at') ? $this->input('discount_ends_at') : $course->discount_ends_at;
                if ($promotion === null && $start === null && $end === null) {
                    return;
                }
                if ($promotion === null || $start === null || $end === null) {
                    $validator->errors()->add('promotional_price', 'A promotion requires its price, start and end.');

                    return;
                }
                if (BigDecimal::of((string) $promotion)->compareTo(BigDecimal::of($price)) >= 0) {
                    $validator->errors()->add('promotional_price', 'Promotional price must be below the regular price.');
                }
                if (Carbon::parse($start)->greaterThanOrEqualTo(Carbon::parse($end))) {
                    $validator->errors()->add('discount_ends_at', 'Promotion end must follow its start.');
                }
            },
        ];
    }
}
