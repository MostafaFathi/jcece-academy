<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Course;
use App\Models\Package;
use App\Services\CommercePricingService;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCouponRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('coupons.manage') ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'min:3', 'max:64', 'regex:/^[A-Z0-9_-]+$/', Rule::unique('coupons', 'code')->ignore($this->route('coupon'))],
            'is_active' => ['required', 'boolean'],
            'discount_type' => ['required', Rule::in(['fixed', 'percentage'])],
            'discount_value' => ['required', 'numeric', 'gt:0', 'decimal:0,2', 'max:9999999999.99'],
            'starts_at' => ['nullable', 'date'],
            'expires_at' => ['nullable', 'date'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'minimum_order_amount' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
            'applies_to' => ['required', Rule::in(['all', 'course', 'package'])],
            'product_ids' => ['nullable', 'array'],
            'product_ids.*' => ['integer', 'min:1', 'distinct'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge(['code' => app(CommercePricingService::class)->normalizeCode((string) $this->input('code'))]);
        }
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $validator->errors()->has('discount_value') && $this->input('discount_type') === 'percentage' && BigDecimal::of((string) $this->input('discount_value', '0'))->compareTo(100) > 0) {
                $validator->errors()->add('discount_value', 'Percentage cannot exceed 100.');
            }
            $scope = $this->input('applies_to');
            $ids = $this->input('product_ids', []);
            if ($scope !== 'all' && (! is_array($ids) || $ids === [])) {
                $validator->errors()->add('product_ids', 'Select at least one eligible product.');
            }
            if ($scope === 'all' && $ids !== []) {
                $validator->errors()->add('product_ids', 'All-product coupons cannot specify products.');
            }
            if (in_array($scope, ['course', 'package'], true) && is_array($ids)) {
                $model = $scope === 'course' ? Course::class : Package::class;
                if ($model::query()->whereIn('id', $ids)->count() !== count(array_unique($ids))) {
                    $validator->errors()->add('product_ids', 'Some selected products do not exist.');
                }
            }
            if (! $validator->errors()->hasAny(['starts_at', 'expires_at']) && $this->input('starts_at') !== null && $this->input('expires_at') !== null
                && Carbon::parse($this->input('starts_at'))->greaterThanOrEqualTo(Carbon::parse($this->input('expires_at')))) {
                $validator->errors()->add('expires_at', 'Expiry must follow the start.');
            }
        }];
    }
}
