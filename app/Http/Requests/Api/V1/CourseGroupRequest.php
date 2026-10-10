<?php

namespace App\Http\Requests\Api\V1;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CourseGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('messaging.groups.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'title' => [$this->isMethod('POST') ? 'required' : 'sometimes', 'string', 'max:120'],
            'kind' => [$this->isMethod('POST') ? 'required' : 'prohibited', Rule::in(['all', 'selected'])],
            'student_ids' => ['required_if:kind,selected', 'array', 'max:200'],
            'student_ids.*' => ['integer', 'distinct'],
        ];
    }
}
