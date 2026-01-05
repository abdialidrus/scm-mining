<?php

namespace App\Http\Requests\Api\WarehouseLocation;

use App\Models\WarehouseLocation;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class UpdateWarehouseLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        /** @var WarehouseLocation $location */
        $location = $this->route('warehouse_location');
        $warehouseId = $this->input('warehouse_id', $location->warehouse_id);

        return [
            'warehouse_id' => [
                'sometimes',
                'integer',
                'exists:warehouses,id',
                // Cannot change warehouse if location has stock
                function ($attribute, $value, $fail) use ($location) {
                    if ($value != $location->warehouse_id) {
                        $hasStock = DB::table('stock_balances')
                            ->where('location_id', $location->id)
                            ->where('qty_on_hand', '>', 0)
                            ->exists();

                        if ($hasStock) {
                            $fail('Cannot change warehouse because this location has stock.');
                        }
                    }
                },
            ],
            'parent_id' => [
                'nullable',
                'integer',
                'exists:warehouse_locations,id',
                // Cannot set self as parent
                function ($attribute, $value, $fail) use ($location) {
                    if ($value == $location->id) {
                        $fail('Cannot set location as its own parent.');
                    }
                },
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
            'type' => ['sometimes', 'string', Rule::in([WarehouseLocation::TYPE_RECEIVING, WarehouseLocation::TYPE_STORAGE])],
            'code' => [
                'sometimes',
                'string',
                'max:50',
                // Code must be unique per warehouse
                Rule::unique('warehouse_locations')->where(function ($query) use ($warehouseId) {
                    return $query->where('warehouse_id', $warehouseId);
                })->ignore($location->id),
            ],
            'name' => ['sometimes', 'string', 'max:255'],
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
            'warehouse_id.exists' => 'Selected warehouse does not exist.',
            'type.in' => 'Location type must be RECEIVING or STORAGE.',
            'code.unique' => 'Location code already exists in this warehouse.',
        ];
    }
}
