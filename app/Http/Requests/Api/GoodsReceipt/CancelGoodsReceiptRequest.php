<?php

namespace App\Http\Requests\Api\GoodsReceipt;

use Illuminate\Foundation\Http\FormRequest;

class CancelGoodsReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
