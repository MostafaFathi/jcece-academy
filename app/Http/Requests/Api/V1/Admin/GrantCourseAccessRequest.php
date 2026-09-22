<?php

namespace App\Http\Requests\Api\V1\Admin;

use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GrantCourseAccessRequest extends FormRequest
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
            'access_starts_at' => ['sometimes', 'required', 'date'],
            'access_expires_at' => ['sometimes', 'nullable', 'date'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['access_starts_at', 'access_expires_at']) || $this->input('access_expires_at') === null) {
                    return;
                }

                $startsAt = $this->filled('access_starts_at')
                    ? Carbon::parse($this->input('access_starts_at'))
                    : now();
                $expiresAt = Carbon::parse($this->input('access_expires_at'));

                if ($expiresAt->lte($startsAt)) {
                    $validator->errors()->add('access_expires_at', 'The access expiration must be after the access start.');
                }
            },
        ];
    }
}
