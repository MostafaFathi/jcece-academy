<?php

namespace App\Http\Requests\Api\V1\Me;

use App\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePaymentRequest extends FormRequest
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
            'method' => ['required', Rule::enum(PaymentMethod::class)],
            'transaction_id' => ['nullable', 'string', 'max:255'],
            'payment_proof' => [
                'required',
                'file',
                'mimes:pdf,jpg,jpeg,png',
                'max:'.config('jcec.commerce.payment_proof_max_kilobytes', 5120),
            ],
        ];
    }
}
