<?php

namespace App\Http\Requests\Api\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePurchaseOrderDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'currency_code' => ['required', 'string', 'max:10'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:1'],

            // Only allow updating unit_price (and optionally remarks) for existing lines.
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.id' => ['required', 'integer', 'exists:purchase_order_lines,id'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.remarks' => ['nullable', 'string'],
        ];
    }
}
