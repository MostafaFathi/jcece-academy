<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UploadLessonResourceRequest extends FormRequest
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
        $extensions = implode(',', config('jcec.lesson_resources.mimes'));

        return [
            'title' => ['required', 'string', 'max:255'],
            'file' => ['required', 'file', 'mimes:'.$extensions, 'extensions:'.$extensions, 'max:'.config('jcec.lesson_resources.max_kilobytes')],
            'is_downloadable' => ['sometimes', 'boolean'],
        ];
    }
}
