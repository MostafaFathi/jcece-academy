<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Package;
use App\PackageStatus;
use App\PackageType;
use Brick\Math\BigDecimal;
use Carbon\Carbon;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdatePackageRequest extends FormRequest
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
        /** @var Package $package */
        $package = $this->route('package');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash', Rule::unique('packages', 'slug')->ignore($package)],
            'description' => ['nullable', 'string'],
            'thumbnail' => ['nullable', 'string', 'max:2048'],
            'type' => ['sometimes', 'required', Rule::enum(PackageType::class)],
            'price' => ['sometimes', 'required', 'numeric', 'min:0', 'max:9999999999.99'],
            'compare_price' => ['nullable', 'numeric', 'max:9999999999.99'],
            'promotional_price' => ['nullable', 'numeric', 'min:0', 'decimal:0,2', 'max:9999999999.99'],
            'discount_starts_at' => ['nullable', 'date'],
            'discount_ends_at' => ['nullable', 'date'],
            'access_duration_days' => ['nullable', 'integer', 'min:1'],
            'is_sequential' => ['sometimes', 'boolean'],
            'sequential_completion_percentage' => ['nullable', 'numeric', 'gt:0', 'lte:100', 'decimal:0,2', Rule::requiredIf(fn (): bool => $this->boolean('is_sequential', $package->is_sequential) && $package->sequential_completion_percentage === null)],
            'status' => ['sometimes', 'required', Rule::enum(PackageStatus::class)],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['price', 'compare_price', 'promotional_price', 'discount_starts_at', 'discount_ends_at'])) {
                    return;
                }

                /** @var Package $package */
                $package = $this->route('package');
                $price = BigDecimal::of((string) $this->input('price', $package->price));
                $comparePrice = $this->has('compare_price') ? $this->input('compare_price') : $package->compare_price;

                if ($comparePrice !== null && BigDecimal::of((string) $comparePrice)->compareTo($price) < 0) {
                    $validator->errors()->add('compare_price', 'The compare price must be greater than or equal to the price.');
                }

                $promotion = $this->exists('promotional_price') ? $this->input('promotional_price') : $package->promotional_price;
                $start = $this->exists('discount_starts_at') ? $this->input('discount_starts_at') : $package->discount_starts_at;
                $end = $this->exists('discount_ends_at') ? $this->input('discount_ends_at') : $package->discount_ends_at;
                if ($promotion !== null || $start !== null || $end !== null) {
                    if ($promotion === null || $start === null || $end === null) {
                        $validator->errors()->add('promotional_price', 'A promotion requires its price, start and end.');
                    } elseif (BigDecimal::of((string) $promotion)->compareTo($price) >= 0) {
                        $validator->errors()->add('promotional_price', 'Promotional price must be below regular price.');
                    }
                    if ($start !== null && $end !== null && Carbon::parse($start)->greaterThanOrEqualTo(Carbon::parse($end))) {
                        $validator->errors()->add('discount_ends_at', 'Promotion end must follow its start.');
                    }
                }

                if ($this->boolean('is_sequential', $package->is_sequential)
                    && $this->exists('sequential_completion_percentage')
                    && $this->input('sequential_completion_percentage') === null) {
                    $validator->errors()->add('sequential_completion_percentage', 'A sequential package requires a completion percentage.');
                }
            },
        ];
    }
}
