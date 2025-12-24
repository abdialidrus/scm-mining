<?php

namespace App\Http\Requests\Api\PutAway;

use Illuminate\Foundation\Http\FormRequest;

class StorePutAwayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'goods_receipt_id' => ['required', 'integer', 'exists:goods_receipts,id'],
            'put_away_at' => ['nullable', 'date'],
            'remarks' => ['nullable', 'string'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.goods_receipt_line_id' => ['required', 'integer', 'exists:goods_receipt_lines,id'],
            'lines.*.destination_location_id' => ['required', 'integer', 'exists:warehouse_locations,id'],
            'lines.*.qty' => ['required', 'numeric', 'gt:0'],
            'lines.*.remarks' => ['nullable', 'string'],
        ];
    }
}
