<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\User;
use App\RoleName;
use App\UserStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\Validator;

class UpdateUserRequest extends FormRequest
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
        /** @var User $user */
        $user = $this->route('user');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'password' => ['sometimes', 'nullable', 'confirmed', Password::defaults()],
            'status' => ['sometimes', Rule::enum(UserStatus::class)],
            'roles' => ['sometimes', 'required', 'array', 'min:1'],
            'roles.*' => ['required', 'string', 'distinct', Rule::in(array_column(RoleName::cases(), 'value'))],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                /** @var User $user */
                $user = $this->route('user');
                if ($this->has('roles') && is_array($this->input('roles')) && ! $validator->errors()->has('roles')) {
                    $newInstructor = in_array(RoleName::Instructor->value, $this->input('roles'), true);
                    if ($newInstructor !== $user->hasRole(RoleName::Instructor->value)) {
                        $validator->errors()->add('roles', 'Use the instructor management workflow to change instructor membership.');
                    }

                    if ($user->is($this->user()) && $user->hasRole(RoleName::Admin->value) && ! in_array(RoleName::Admin->value, $this->input('roles'), true)) {
                        $validator->errors()->add('roles', 'You cannot remove your own administrator role.');
                    }
                }

                if ($user->is($this->user()) && $user->hasRole(RoleName::Admin->value) && $this->has('status') && ! $validator->errors()->has('status') && $this->input('status') !== UserStatus::Active->value) {
                    $validator->errors()->add('status', 'You cannot deactivate your own administrator account.');
                }
            },
        ];
    }
}
