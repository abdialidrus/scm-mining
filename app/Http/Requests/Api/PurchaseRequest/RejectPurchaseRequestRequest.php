<?php

namespace App\Http\Requests\Api\PurchaseRequest;

use Illuminate\Foundation\Http\FormRequest;

class RejectPurchaseRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'reason' => ['required', 'string', 'min:3', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Reject reason is required.',
            'reason.min' => 'Reject reason must be at least :min characters.',
            'reason.max' => 'Reject reason may not be greater than :max characters.',
        ];
    }
}
