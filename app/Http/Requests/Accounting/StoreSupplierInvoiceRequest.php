<?php

namespace App\Http\Requests\Accounting;

use App\Enums\Accounting\InvoiceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupplierInvoiceRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Models\Accounting\SupplierInvoice::class);
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            // Invoice Header
            'purchase_order_id' => ['required', 'exists:purchase_orders,id'],
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'invoice_number' => [
                'required',
                'string',
                'max:100',
                Rule::unique('supplier_invoices')
                    ->where('supplier_id', $this->supplier_id)
            ],
            'invoice_date' => ['required', 'date', 'before_or_equal:today'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'tax_invoice_number' => ['nullable', 'string', 'max:100'],
            'tax_invoice_date' => ['nullable', 'date'],

            // Financial Information
            'subtotal' => ['required', 'numeric', 'min:0'],
            'tax_amount' => ['required', 'numeric', 'min:0'],
            'discount_amount' => ['nullable', 'numeric', 'min:0'],
            'other_charges' => ['nullable', 'numeric', 'min:0'],
            'total_amount' => ['required', 'numeric', 'min:0'],

            // Additional Info
            'notes' => ['nullable', 'string', 'max:1000'],
            'delivery_note_number' => ['nullable', 'string', 'max:100'],
            'currency' => ['required', 'string', 'in:IDR'], // Only IDR supported
            'exchange_rate' => ['nullable', 'numeric', 'min:0'],
            'status' => ['required', Rule::enum(InvoiceStatus::class)],

            // Invoice Lines
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_id' => ['required', 'exists:items,id'],
            'lines.*.uom_id' => ['required', 'exists:uoms,id'],
            'lines.*.purchase_order_line_id' => ['required', 'exists:purchase_order_lines,id'],
            'lines.*.goods_receipt_line_id' => ['nullable', 'exists:goods_receipt_lines,id'],
            'lines.*.description' => ['nullable', 'string', 'max:500'],
            'lines.*.invoiced_qty' => ['required', 'numeric', 'min:0.01'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
            'lines.*.line_total' => ['required', 'numeric', 'min:0'],
            'lines.*.tax_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.discount_amount' => ['nullable', 'numeric', 'min:0'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],

            // File Uploads
            'invoice_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'], // 10MB
            'tax_invoice_file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'purchase_order_id.required' => 'Purchase Order harus dipilih',
            'purchase_order_id.exists' => 'Purchase Order tidak ditemukan',
            'supplier_id.required' => 'Supplier harus dipilih',
            'supplier_id.exists' => 'Supplier tidak ditemukan',
            'invoice_number.required' => 'Nomor Invoice harus diisi',
            'invoice_number.unique' => 'Nomor Invoice sudah ada untuk supplier ini',
            'invoice_date.required' => 'Tanggal Invoice harus diisi',
            'invoice_date.before_or_equal' => 'Tanggal Invoice tidak boleh lebih dari hari ini',
            'due_date.required' => 'Tanggal Jatuh Tempo harus diisi',
            'due_date.after_or_equal' => 'Tanggal Jatuh Tempo tidak boleh kurang dari Tanggal Invoice',
            'currency.in' => 'Hanya mata uang IDR yang didukung',
            'lines.required' => 'Invoice harus memiliki minimal 1 item',
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
        // Auto-calculate total_amount if not provided
        if (!$this->has('total_amount') && $this->has('subtotal')) {
            $subtotal = (float) $this->subtotal;
            $taxAmount = (float) ($this->tax_amount ?? 0);
            $discount = (float) ($this->discount_amount ?? 0);
            $otherCharges = (float) ($this->other_charges ?? 0);

            $this->merge([
                'total_amount' => $subtotal + $taxAmount - $discount + $otherCharges,
            ]);
        }

        // Auto-calculate line_total for each line if not provided
        if ($this->has('lines')) {
            $lines = $this->lines;
            foreach ($lines as $index => $line) {
                if (!isset($line['line_total']) && isset($line['invoiced_qty']) && isset($line['unit_price'])) {
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
