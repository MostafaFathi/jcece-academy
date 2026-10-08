<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\LessonType;
use App\Models\CourseSection;
use App\Models\Lesson;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateLessonRequest extends FormRequest
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
        /** @var Lesson $lesson */
        $lesson = $this->route('lesson');
        $type = $this->input('type', $lesson->type->value);

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash', Rule::unique('lessons', 'slug')->where('course_section_id', $section->id)->ignore($lesson)],
            'type' => ['sometimes', 'required', Rule::enum(LessonType::class)],
            'description' => ['nullable', 'string'],
            'content' => ['nullable', 'string'],
            'video_provider' => ['nullable', 'string', 'max:255', Rule::prohibitedIf(fn (): bool => $type !== LessonType::Video->value || ! $this->boolean('is_preview', $lesson->is_preview))],
            'video_id' => ['nullable', 'string', 'max:255', Rule::prohibitedIf(fn (): bool => $type !== LessonType::Video->value || ! $this->boolean('is_preview', $lesson->is_preview))],
            'video_url' => ['nullable', 'url', 'max:2048', Rule::prohibitedIf(fn (): bool => ! in_array($type, [LessonType::Video->value, LessonType::Link->value], true) || ($type === LessonType::Video->value && ! $this->boolean('is_preview', $lesson->is_preview)))],
            'protected_video_asset_key' => ['prohibited'],
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

                /** @var Lesson $lesson */
                $lesson = $this->route('lesson');
                $type = $this->input('type', $lesson->type->value);
                $content = $this->input('content', $lesson->content);
                $videoId = $this->input('video_id', $lesson->video_id);
                $videoUrl = $this->input('video_url', $lesson->video_url);
                if ($lesson->video_provider === 'bunny_stream' && ($type !== LessonType::Video->value || $this->boolean('is_preview', $lesson->is_preview))) {
                    $validator->errors()->add('type', 'Remove the protected video before changing this lesson type or visibility.');
                }

                if ($type === LessonType::Text->value && blank($content)) {
                    $validator->errors()->add('content', 'Content is required for text lessons.');
                }

                if ($type === LessonType::Video->value && $this->boolean('is_preview', $lesson->is_preview) && blank($videoId) && blank($videoUrl)) {
                    $validator->errors()->add('video_url', 'A public preview video URL is required.');
                }

                if ($type === LessonType::Link->value && blank($videoUrl)) {
                    $validator->errors()->add('video_url', 'A URL is required for link lessons.');
                }
            },
        ];
    }
}
