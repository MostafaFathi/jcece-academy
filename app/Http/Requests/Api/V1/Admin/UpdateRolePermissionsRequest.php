<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\PermissionName;
use App\RoleName;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRolePermissionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->hasRole(RoleName::Admin->value)
            && $this->user()->can(PermissionName::RolesManage->value);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            'permissions.*' => ['required', 'string', 'distinct', Rule::in(array_column(PermissionName::cases(), 'value')), Rule::exists('permissions', 'name')->where('guard_name', 'web')],
            'version' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($this->route('role') !== RoleName::Admin->value && in_array(PermissionName::RolesManage->value, (array) $this->input('permissions', []), true)) {
                $validator->errors()->add('permissions', 'Role management is restricted to the administrator role.');
            }
        }];
    }
}
