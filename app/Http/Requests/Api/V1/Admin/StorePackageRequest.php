<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\PackageStatus;
use App\PackageType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'access_duration_days' => ['nullable', 'integer', 'min:1'],
            'is_sequential' => ['sometimes', 'boolean'],
            'status' => ['sometimes', Rule::enum(PackageStatus::class)],
            'published_at' => ['nullable', 'date'],
        ];
    }
}
