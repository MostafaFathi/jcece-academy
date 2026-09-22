<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\LessonType;
use App\Models\CourseSection;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreLessonRequest extends FormRequest
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
        /** @var CourseSection $section */
        $section = $this->route('section');

        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('lessons', 'slug')->where('course_section_id', $section->id)],
            'type' => ['required', Rule::enum(LessonType::class)],
            'description' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'video_provider' => ['nullable', 'string', 'max:255', Rule::prohibitedIf(fn (): bool => $this->input('type') !== LessonType::Video->value)],
            'video_id' => ['nullable', 'string', 'max:255', Rule::prohibitedIf(fn (): bool => $this->input('type') !== LessonType::Video->value)],
            'video_url' => ['nullable', 'url', 'max:2048', Rule::prohibitedIf(fn (): bool => ! in_array($this->input('type'), [LessonType::Video->value, LessonType::Link->value], true))],
            'duration_seconds' => ['nullable', 'integer', 'min:0'],
            'is_preview' => ['sometimes', 'boolean'],
            'is_published' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->has('type')) {
                    return;
                }

                $type = $this->string('type')->toString();

                if ($type === LessonType::Text->value && ! $this->filled('content')) {
                    $validator->errors()->add('content', 'Content is required for text lessons.');
                }

                if ($type === LessonType::Video->value && ! $this->filled('video_id') && ! $this->filled('video_url')) {
                    $validator->errors()->add('video_url', 'A video ID or video URL is required for video lessons.');
                }

                if ($type === LessonType::Link->value && ! $this->filled('video_url')) {
                    $validator->errors()->add('video_url', 'A URL is required for link lessons.');
                }
            },
        ];
    }
}
