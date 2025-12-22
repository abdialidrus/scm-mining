<?php

namespace App\Http\Requests\Api\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class StorePurchaseOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'supplier_id' => ['required', 'integer', 'exists:suppliers,id'],
            'purchase_request_ids' => ['required', 'array', 'min:1'],
            'purchase_request_ids.*' => ['integer', 'exists:purchase_requests,id'],
            'currency_code' => ['nullable', 'string', 'max:10'],
            'tax_rate' => ['nullable', 'numeric', 'min:0', 'max:1'],

            // Optional override lines. If omitted -> merged from PRs.
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.item_id' => ['required_with:lines', 'integer', 'exists:items,id'],
            'lines.*.quantity' => ['required_with:lines', 'numeric', 'gt:0'],
            'lines.*.uom_id' => ['required_with:lines', 'integer', 'exists:uoms,id'],
            'lines.*.unit_price' => ['nullable', 'numeric', 'min:0'],
            'lines.*.remarks' => ['nullable', 'string'],
        ];
    }
}
