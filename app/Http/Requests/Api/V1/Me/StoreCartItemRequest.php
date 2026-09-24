<?php

namespace App\Http\Requests\Api\V1\Me;

use App\PurchasableType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCartItemRequest extends FormRequest
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
            'purchasable_type' => ['required', Rule::enum(PurchasableType::class)],
            'purchasable_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
