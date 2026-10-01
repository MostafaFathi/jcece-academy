<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\User;
use App\UserStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateInstructorRequest extends FormRequest
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
        /** @var User $instructor */
        $instructor = $this->route('instructor');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($instructor)],
            'password' => ['sometimes', 'nullable', 'confirmed', Password::defaults()],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
            'job_title' => ['nullable', 'string', 'max:255'],
            'short_bio' => ['nullable', 'string', 'max:1000'],
            'bio' => ['nullable', 'string', 'max:10000'],
            'years_experience' => ['nullable', 'integer', 'between:0,80'],
            'specialties' => ['sometimes', 'array', 'max:30'],
            'specialties.*' => ['required', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'url', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'website_url' => ['nullable', 'url', 'max:255'],
            'is_featured' => ['sometimes', 'boolean'],
        ];
    }
}
