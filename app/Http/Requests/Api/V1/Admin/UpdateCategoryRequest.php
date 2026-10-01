<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\Category;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateCategoryRequest extends FormRequest
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
        /** @var Category $category */
        $category = $this->route('category');

        return [
            'parent_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->whereNull('deleted_at'), Rule::notIn([$category->id])],
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'required', 'string', 'max:255', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($category)],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'string', 'max:2048'],
            'icon' => ['nullable', 'string', 'max:255'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /** @return array<int, callable(Validator): void> */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if (! $this->has('parent_id') || $this->input('parent_id') === null || $validator->errors()->has('parent_id')) {
                    return;
                }

                /** @var Category $category */
                $category = $this->route('category');
                $parentId = $this->integer('parent_id');
                $visited = [];

                while ($parentId !== 0 && ! isset($visited[$parentId])) {
                    if ($parentId === $category->id) {
                        $validator->errors()->add('parent_id', 'A category cannot be moved under itself or one of its descendants.');

                        return;
                    }

                    $visited[$parentId] = true;
                    $parentId = (int) (Category::query()->whereKey($parentId)->value('parent_id') ?? 0);
                }

                if ($parentId !== 0) {
                    $validator->errors()->add('parent_id', 'The selected category hierarchy contains a cycle.');
                }
            },
        ];
    }
}
