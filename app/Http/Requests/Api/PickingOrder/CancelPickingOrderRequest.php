<?php

namespace App\Http\Requests\Api\PickingOrder;

use Illuminate\Foundation\Http\FormRequest;

class CancelPickingOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }
}
