<?php

namespace App\Http\Requests\Api\Warehouse;

use Illuminate\Foundation\Http\FormRequest;

class UpdateWarehouseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $warehouseId = (int) $this->route('warehouse')?->id;

        return [
            'code' => ['sometimes', 'string', 'max:50', 'unique:warehouses,code,' . $warehouseId],
            'name' => ['sometimes', 'string', 'max:255'],
            'address' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
