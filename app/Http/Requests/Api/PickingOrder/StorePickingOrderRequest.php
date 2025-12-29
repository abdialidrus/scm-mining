<?php

namespace App\Http\Requests\Api\PickingOrder;

use Illuminate\Foundation\Http\FormRequest;

class StorePickingOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'department_id' => ['nullable', 'integer', 'exists:departments,id'],
            'purpose' => ['nullable', 'string', 'max:100'],
            'picked_at' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'integer', 'exists:items,id'],
            'lines.*.uom_id' => ['nullable', 'integer', 'exists:uoms,id'],
            'lines.*.source_location_id' => ['required', 'integer', 'exists:warehouse_locations,id'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.remarks' => ['nullable', 'string'],
        ];
    }
}
