<?php

namespace App\Http\Requests\Api\ItemCategory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateItemCategoryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $categoryId = $this->route('itemCategory')?->id ?? $this->route('itemCategory');

        return [
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('item_categories', 'code')->ignore($categoryId)],
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'parent_id' => ['nullable', 'integer', 'exists:item_categories,id'],
            'is_active' => ['sometimes', 'boolean'],
            'requires_approval' => ['sometimes', 'boolean'],
            'color_code' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}
