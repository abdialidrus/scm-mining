<?php

namespace App\Http\Requests\Api\WarehouseLocation;

use App\Models\WarehouseLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreWarehouseLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $warehouseId = $this->input('warehouse_id');

        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:warehouse_locations,id',
                // Validate parent is in same warehouse
                function ($attribute, $value, $fail) use ($warehouseId) {
                    if ($value && $warehouseId) {
                        $parent = WarehouseLocation::find($value);
                        if ($parent && $parent->warehouse_id != $warehouseId) {
                            $fail('Parent location must be in the same warehouse.');
                        }
                    }
                },
            ],
            'type' => ['required', 'string', Rule::in([WarehouseLocation::TYPE_RECEIVING, WarehouseLocation::TYPE_STORAGE])],
            'code' => [
                'required',
                'string',
                'max:50',
                // Code must be unique per warehouse
                Rule::unique('warehouse_locations')->where(function ($query) use ($warehouseId) {
                    return $query->where('warehouse_id', $warehouseId);
                }),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_default' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
            'capacity' => ['nullable', 'numeric', 'min:0'],
            'max_weight' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'warehouse_id.required' => 'Warehouse is required.',
            'warehouse_id.exists' => 'Selected warehouse does not exist.',
            'type.required' => 'Location type is required.',
            'type.in' => 'Location type must be RECEIVING or STORAGE.',
            'code.required' => 'Location code is required.',
            'code.unique' => 'Location code already exists in this warehouse.',
            'name.required' => 'Location name is required.',
        ];
    }
}
