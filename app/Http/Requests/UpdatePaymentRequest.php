<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdatePaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->hasAnyRole(['finance', 'super_admin']);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Supplier Invoice Info
            'supplier_invoice_number' => 'required|string|max:100',
            'supplier_invoice_date' => 'required|date',
            'supplier_invoice_amount' => 'required|numeric|min:0|max:999999999999.99',
            'supplier_invoice_file' => 'nullable|file|mimes:pdf|max:10240', // 10MB max

            // Payment Info
            'payment_date' => 'required|date|before_or_equal:today',
            'payment_amount' => 'required|numeric|min:0.01|max:999999999999.99',
            'payment_method' => 'required|in:TRANSFER,CASH,CHECK,GIRO',
            'payment_reference' => 'nullable|string|max:100',
            'payment_proof_file' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120', // 5MB max

            // Bank Info
            'bank_account_from' => 'nullable|string|max:100',
            'bank_account_to' => 'nullable|string|max:100',

            // Notes
            'notes' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Get custom attribute names for validation errors
     */
    public function attributes(): array
    {
        return [
            'supplier_invoice_number' => 'invoice number',
            'supplier_invoice_date' => 'invoice date',
            'supplier_invoice_amount' => 'invoice amount',
            'supplier_invoice_file' => 'invoice file',
            'payment_date' => 'payment date',
            'payment_amount' => 'payment amount',
            'payment_method' => 'payment method',
            'payment_reference' => 'payment reference',
            'payment_proof_file' => 'payment proof',
            'bank_account_from' => 'from bank account',
            'bank_account_to' => 'to bank account',
        ];
    }

    /**
     * Get custom validation messages
     */
    public function messages(): array
    {
        return [
            'supplier_invoice_file.mimes' => 'Invoice file must be a PDF document.',
            'supplier_invoice_file.max' => 'Invoice file must not exceed 10MB.',
            'payment_proof_file.mimes' => 'Payment proof must be a PDF or image file (JPG, PNG).',
            'payment_proof_file.max' => 'Payment proof must not exceed 5MB.',
        ];
    }
}
