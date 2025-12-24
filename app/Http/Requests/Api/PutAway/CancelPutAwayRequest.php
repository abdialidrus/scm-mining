<?php

namespace App\Http\Requests\Api\PutAway;

use Illuminate\Foundation\Http\FormRequest;

class CancelPutAwayRequest extends FormRequest
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
