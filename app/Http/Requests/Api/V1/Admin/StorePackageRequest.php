<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\PackageStatus;
use App\PackageType;
use Brick\Math\BigDecimal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorePackageRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash', Rule::unique('packages', 'slug')],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'string', 'max:2048'],
            'type' => ['required', Rule::enum(PackageType::class)],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999999.99'],
            'compare_price' => ['nullable', 'numeric', 'gte:price', 'max:9999999999.99'],
            'promotional_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
            'discount_starts_at' => ['nullable', 'date'],
            'discount_ends_at' => ['nullable', 'date', 'after:discount_starts_at'],
            'access_duration_days' => ['nullable', 'integer', 'min:1'],
            'is_sequential' => ['sometimes', 'boolean'],
            'sequential_completion_percentage' => ['nullable', 'numeric', 'gt:0', 'lte:100', 'decimal:0,2', Rule::requiredIf(fn (): bool => $this->boolean('is_sequential'))],
            'status' => ['sometimes', Rule::enum(PackageStatus::class)],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->hasAny(['price', 'promotional_price', 'discount_starts_at', 'discount_ends_at'])) {
                return;
            }
            $promotion = $this->input('promotional_price');
            $start = $this->input('discount_starts_at');
            $end = $this->input('discount_ends_at');
            if ($promotion === null && $start === null && $end === null) {
                return;
            }
            if ($promotion === null || $start === null || $end === null) {
                $validator->errors()->add('promotional_price', 'A promotion requires its price, start and end.');

                return;
            }
            if (BigDecimal::of((string) $promotion)->compareTo(BigDecimal::of((string) $this->input('price'))) >= 0) {
                $validator->errors()->add('promotional_price', 'Promotional price must be below regular price.');
            }
        }];
    }
}
