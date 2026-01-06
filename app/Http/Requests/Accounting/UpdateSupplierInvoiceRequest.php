<?php

namespace App\Http\Requests\Accounting;

use App\Enums\Accounting\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplierInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $invoice = $this->route('supplier_invoice');
        return $this->user()->can('update', $invoice);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $invoice = $this->route('supplier_invoice');

        return [
            // Invoice Header
            'purchase_order_id' => ['sometimes', 'exists:purchase_orders,id'],
            'supplier_id' => ['sometimes', 'exists:suppliers,id'],
            'invoice_number' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('supplier_invoices')
                    ->where('supplier_id', $this->supplier_id ?? $invoice->supplier_id)
                    ->ignore($invoice->id)
            ],
            'invoice_date' => ['sometimes', 'date', 'before_or_equal:today'],
            'due_date' => ['sometimes', 'date', 'after_or_equal:invoice_date'],
            'tax_invoice_number' => ['nullable', 'string', 'max:100'],
            'tax_invoice_date' => ['nullable', 'date'],

            // Financial Information
            'subtotal' => ['sometimes', 'numeric', 'min:0'],
            'tax_amount' => ['sometimes', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'other_charges' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['sometimes', 'numeric', 'min:0'],

            // Additional Info
            'notes' => ['nullable', 'string', 'max:1000'],
            'delivery_note_number' => ['nullable', 'string', 'max:100'],
            'currency' => ['sometimes', 'string', 'in:IDR'],
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::enum(InvoiceStatus::class)],

            // Invoice Lines
            'lines' => ['sometimes', 'array', 'min:1'],
            'lines.*.id' => ['nullable', 'exists:supplier_invoice_lines,id'],
            'lines.*.item_id' => ['required_with:lines', 'exists:items,id'],
            'lines.*.uom_id' => ['required_with:lines', 'exists:uoms,id'],
            'lines.*.purchase_order_line_id' => ['required_with:lines', 'exists:purchase_order_lines,id'],
            'lines.*.goods_receipt_line_id' => ['nullable', 'exists:goods_receipt_lines,id'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.invoiced_qty' => ['required_with:lines', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.line_total' => ['required_with:lines', 'numeric', 'min:0'],
            'lines.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],

            // File Uploads
            'invoice_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'tax_invoice_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'purchase_order_id.exists' => 'Purchase Order tidak ditemukan',
            'supplier_id.exists' => 'Supplier tidak ditemukan',
            'invoice_number.unique' => 'Nomor Invoice sudah ada untuk supplier ini',
            'invoice_date.before_or_equal' => 'Tanggal Invoice tidak boleh lebih dari hari ini',
            'due_date.after_or_equal' => 'Tanggal Jatuh Tempo tidak boleh kurang dari Tanggal Invoice',
            'currency.in' => 'Hanya mata uang IDR yang didukung',
            'lines.min' => 'Invoice harus memiliki minimal 1 item',
            'invoice_file.mimes' => 'File Invoice harus berformat PDF, JPG, JPEG, atau PNG',
            'invoice_file.max' => 'Ukuran File Invoice maksimal 10MB',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Auto-calculate total_amount if subtotal is provided
        if ($this->has('subtotal')) {
            $invoice = $this->route('supplier_invoice');

            $subtotal = (float) $this->subtotal;
            $taxAmount = (float) ($this->tax_amount ?? $invoice->tax_amount ?? 0);
            $discount = (float) ($this->discount_amount ?? $invoice->discount_amount ?? 0);
            $otherCharges = (float) ($this->other_charges ?? $invoice->other_charges ?? 0);

            $this->merge([
                'total_amount' => $subtotal + $taxAmount - $discount + $otherCharges,
            ]);
        }

        // Auto-calculate line_total for each line
        if ($this->has('lines')) {
            $lines = $this->lines;
            foreach ($lines as $index => $line) {
                if (isset($line['invoiced_qty']) && isset($line['unit_price'])) {
                    $qty = (float) $line['invoiced_qty'];
                    $price = (float) $line['unit_price'];
                    $lineTax = (float) ($line['tax_amount'] ?? 0);
                    $lineDiscount = (float) ($line['discount_amount'] ?? 0);

                    $lines[$index]['line_total'] = ($qty * $price) + $lineTax - $lineDiscount;
                }
            }
            $this->merge(['lines' => $lines]);
        }
    }
}
