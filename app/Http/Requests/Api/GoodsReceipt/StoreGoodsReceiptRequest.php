<?php

namespace App\Http\Requests\Api\GoodsReceipt;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'purchase_order_id' => ['required', 'integer', 'exists:purchase_orders,id'],
            'warehouse_id' => ['required', 'integer', 'exists:warehouses,id'],
            'received_at' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],

            'lines' => ['required', 'array', 'min:1'],
            'lines.*.purchase_order_line_id' => ['required', 'integer', 'exists:purchase_order_lines,id'],
            'lines.*.received_quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.remarks' => ['nullable', 'string'],
        ];
    }
}
