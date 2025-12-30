<?php

namespace App\Http\Requests\Api\Item;

use Illuminate\Foundation\Http\FormRequest;

class StoreItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:50', 'unique:items,sku'],
            'name' => ['required', 'string', 'max:255'],
            'is_serialized' => ['boolean'],
            'criticality_level' => ['nullable', 'integer', 'min:1', 'max:5'],
            'base_uom_id' => ['required', 'exists:uoms,id'],
            'item_category_id' => ['nullable', 'exists:item_categories,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'sku.required' => 'SKU is required',
            'sku.unique' => 'SKU already exists',
            'name.required' => 'Item name is required',
            'base_uom_id.required' => 'Base UOM is required',
            'base_uom_id.exists' => 'Selected UOM does not exist',
            'item_category_id.exists' => 'Selected category does not exist',
        ];
    }
}
