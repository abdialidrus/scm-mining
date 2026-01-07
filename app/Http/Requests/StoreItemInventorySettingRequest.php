<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreItemInventorySettingRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\ItemInventorySetting::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'item_id' => [
                'required',
                'exists:items,id',
                Rule::unique('item_inventory_settings')
                    ->where('warehouse_id', $this->warehouse_id),
            ],
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'reorder_point' => 'required|numeric|min:0',
            'reorder_quantity' => 'required|numeric|min:0',
            'min_stock' => 'required|numeric|min:0',
            'max_stock' => [
                'nullable',
                'numeric',
                'gt:min_stock',
            ],
            'lead_time_days' => 'required|integer|min:0|max:365',
            'safety_stock' => 'required|numeric|min:0',
            'is_active' => 'boolean',
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'item_id' => 'item',
            'warehouse_id' => 'warehouse',
            'reorder_point' => 'reorder point',
            'reorder_quantity' => 'reorder quantity',
            'min_stock' => 'minimum stock',
            'max_stock' => 'maximum stock',
            'lead_time_days' => 'lead time',
            'safety_stock' => 'safety stock',
            'is_active' => 'active status',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'item_id.unique' => 'Inventory settings for this item and warehouse combination already exists.',
            'max_stock.gt' => 'Maximum stock must be greater than minimum stock.',
        ];
    }
}
