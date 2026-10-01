<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\RoleName;
use App\UserStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListUsersRequest extends FormRequest
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
            'role' => ['sometimes', Rule::enum(RoleName::class)],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ];
    }
}
