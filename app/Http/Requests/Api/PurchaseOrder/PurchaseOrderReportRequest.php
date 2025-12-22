<?php

namespace App\Http\Requests\Api\PurchaseOrder;

use Illuminate\Foundation\Http\FormRequest;

class PurchaseOrderReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            // Date range filters (ISO date: YYYY-MM-DD)
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],

            // Optional filters
            'status' => ['nullable', 'string', 'max:50'],
            'supplier_id' => ['nullable', 'integer', 'exists:suppliers,id'],
            'currency_code' => ['nullable', 'string', 'max:10'],

            // Choose which timestamp to use for date filtering
            'date_field' => ['nullable', 'in:created_at,submitted_at,approved_at,sent_at,closed_at,cancelled_at'],

            // If true, include breakdown by status in response
            'group_by_status' => ['nullable', 'boolean'],
        ];
    }
}
