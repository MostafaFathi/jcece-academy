<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Package;
use App\PackageStatus;
use App\PackageType;
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
            'access_duration_days' => ['nullable', 'integer', 'min:1'],
            'is_sequential' => ['sometimes', 'boolean'],
            'status' => ['sometimes', 'required', Rule::enum(PackageStatus::class)],
            'published_at' => ['nullable', 'date'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->hasAny(['price', 'compare_price'])) {
                    return;
                }

                /** @var Package $package */
                $package = $this->route('package');
                $price = (float) $this->input('price', $package->price);
                $comparePrice = $this->has('compare_price') ? $this->input('compare_price') : $package->compare_price;

                if ($comparePrice !== null && (float) $comparePrice < $price) {
                    $validator->errors()->add('compare_price', 'The compare price must be greater than or equal to the price.');
                }
            },
        ];
    }
}
