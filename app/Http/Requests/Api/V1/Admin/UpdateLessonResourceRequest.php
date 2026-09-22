<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateLessonResourceRequest extends FormRequest
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
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'max:50'],
            'file_path' => ['nullable', 'string', 'max:2048'],
            'external_url' => ['nullable', 'url', 'max:2048'],
            'is_downloadable' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $resource = $this->route('resource');
                $filePath = $this->input('file_path', $resource?->file_path);
                $externalUrl = $this->input('external_url', $resource?->external_url);

                if (blank($filePath) && blank($externalUrl)) {
                    $validator->errors()->add('file_path', 'A file path or external URL is required.');
                }
            },
        ];
    }
}
