<?php

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

class RecordPaymentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $invoice = $this->route('supplier_invoice');
        return $this->user()->can('recordPayment', $invoice);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $invoice = $this->route('supplier_invoice');

        return [
            'payment_date' => ['required', 'date', 'before_or_equal:today'],
            'payment_amount' => [
                'required',
                'numeric',
                'min:0.01',
                'max:' . $invoice->remaining_amount
            ],
            'payment_method' => ['required', 'string', 'in:transfer,cash,check,giro'],
            'bank_name' => ['required_if:payment_method,transfer,check,giro', 'nullable', 'string', 'max:100'],
            'bank_account' => ['nullable', 'string', 'max:100'],
            'reference_number' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'payment_proof' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'], // 5MB
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        $invoice = $this->route('supplier_invoice');

        return [
            'payment_date.required' => 'Tanggal Pembayaran harus diisi',
            'payment_date.before_or_equal' => 'Tanggal Pembayaran tidak boleh lebih dari hari ini',
            'payment_amount.required' => 'Jumlah Pembayaran harus diisi',
            'payment_amount.min' => 'Jumlah Pembayaran minimal Rp 0.01',
            'payment_amount.max' => 'Jumlah Pembayaran tidak boleh lebih dari sisa tagihan (Rp ' . number_format($invoice->remaining_amount ?? 0, 0, ',', '.') . ')',
            'payment_method.required' => 'Metode Pembayaran harus dipilih',
            'payment_method.in' => 'Metode Pembayaran tidak valid',
            'bank_name.required_if' => 'Nama Bank harus diisi untuk metode pembayaran ini',
            'reference_number.required' => 'Nomor Referensi harus diisi',
            'payment_proof.mimes' => 'Bukti Pembayaran harus berformat PDF, JPG, JPEG, atau PNG',
            'payment_proof.max' => 'Ukuran Bukti Pembayaran maksimal 5MB',
        ];
    }
}
