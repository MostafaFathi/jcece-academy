<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRefundRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('refunds.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
            'reason' => ['required', 'string', 'max:64'],
            'access_effect' => ['required', Rule::in(['none', 'items', 'full'])],
            'order_item_ids' => ['nullable', 'array'],
            'order_item_ids.*' => ['integer', 'min:1', 'distinct'],
            'internal_note' => ['nullable', 'string', 'max:2000'],
            'external_reference' => ['nullable', 'string', 'max:255'],
        ];
    }
}
