<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierInvoiceResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'internal_number' => $this->internal_number,
            'invoice_number' => $this->invoice_number,
            'invoice_date' => $this->invoice_date?->format('Y-m-d'),
            'due_date' => $this->due_date?->format('Y-m-d'),

            // Status
            'status' => $this->status ? [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ] : null,
            'matching_status' => $this->matching_status ? [
                'value' => $this->matching_status->value,
                'label' => $this->matching_status->label(),
                'color' => $this->matching_status->color(),
            ] : null,
            'payment_status' => $this->payment_status ? [
                'value' => $this->payment_status->value,
                'label' => $this->payment_status->label(),
                'color' => $this->payment_status->color(),
            ] : null,

            // Relationships
            'supplier' => [
                'id' => $this->supplier->id,
                'code' => $this->supplier->code,
                'name' => $this->supplier->name,
            ],
            'purchase_order' => $this->whenLoaded('purchaseOrder', [
                'id' => $this->purchaseOrder->id,
                'po_number' => $this->purchaseOrder->po_number,
            ]),

            // Financial Info
            'subtotal' => $this->subtotal,
            'tax_amount' => $this->tax_amount,
            'discount_amount' => $this->discount_amount,
            'other_charges' => $this->other_charges,
            'total_amount' => $this->total_amount,
            'paid_amount' => $this->paid_amount,
            'remaining_amount' => $this->remaining_amount,
            'currency' => $this->currency,

            // Tax Info
            'tax_invoice_number' => $this->tax_invoice_number,
            'tax_invoice_date' => $this->tax_invoice_date?->format('Y-m-d'),

            // Additional Info
            'notes' => $this->notes,
            'delivery_note_number' => $this->delivery_note_number,

            // Files
            'invoice_file_path' => $this->invoice_file_path,
            'tax_invoice_file_path' => $this->tax_invoice_file_path,
            'has_invoice_file' => !is_null($this->invoice_file_path),
            'has_tax_invoice_file' => !is_null($this->tax_invoice_file_path),

            // Matching Info
            'requires_approval' => $this->requires_approval,
            'matched_at' => $this->matched_at?->format('Y-m-d H:i:s'),
            'matched_by' => $this->whenLoaded('matchedBy', [
                'id' => $this->matchedBy?->id,
                'name' => $this->matchedBy?->name,
            ]),

            // Approval Info
            'approved_at' => $this->approved_at?->format('Y-m-d H:i:s'),
            'approved_by' => $this->whenLoaded('approvedBy', [
                'id' => $this->approvedBy?->id,
                'name' => $this->approvedBy?->name,
            ]),
            'approval_notes' => $this->approval_notes,

            // Rejection Info
            'rejected_at' => $this->rejected_at?->format('Y-m-d H:i:s'),
            'rejected_by' => $this->whenLoaded('rejectedBy', [
                'id' => $this->rejectedBy?->id,
                'name' => $this->rejectedBy?->name,
            ]),
            'rejection_reason' => $this->rejection_reason,

            // Cancellation Info
            'cancelled_at' => $this->cancelled_at?->format('Y-m-d H:i:s'),
            'cancelled_by' => $this->whenLoaded('cancelledBy', [
                'id' => $this->cancelledBy?->id,
                'name' => $this->cancelledBy?->name,
            ]),
            'cancellation_reason' => $this->cancellation_reason,

            // Submission Info
            'submitted_at' => $this->submitted_at?->format('Y-m-d H:i:s'),
            'submitted_by' => $this->whenLoaded('submittedBy', [
                'id' => $this->submittedBy?->id,
                'name' => $this->submittedBy?->name,
            ]),

            // Lines
            'lines' => SupplierInvoiceLineResource::collection($this->whenLoaded('lines')),

            // Matching Result
            'matching_result' => $this->whenLoaded('matchingResult', function () {
                // Check if matchingResult exists (it's a hasOne relationship that might be null)
                if (!$this->matchingResult) {
                    return null;
                }

                return [
                    'id' => $this->matchingResult->id,
                    // overall_status is a plain string field, not an enum
                    'overall_status' => $this->matchingResult->overall_status,
                    'total_quantity_variance' => $this->matchingResult->total_quantity_variance,
                    'total_price_variance' => $this->matchingResult->total_price_variance,
                    'total_amount_variance' => $this->matchingResult->total_amount_variance,
                    'quantity_variance_percent' => $this->matchingResult->quantity_variance_percent,
                    'price_variance_percent' => $this->matchingResult->price_variance_percent,
                    'amount_variance_percent' => $this->matchingResult->amount_variance_percent,
                    'matched_at' => $this->matchingResult->created_at->format('Y-m-d H:i:s'),
                    'matching_details' => $this->matchingResult->matching_details,
                ];
            }),

            // Payments
            'payments' => InvoicePaymentResource::collection($this->whenLoaded('payments')),
            'payments_count' => $this->whenCounted('payments'),

            // Helper flags
            'is_editable' => $this->isEditable(),
            'can_be_matched' => $this->canBeMatched(),
            'is_overdue' => $this->isOverdue(),

            // Audit Info
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
            'created_by' => $this->whenLoaded('createdBy', [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
            ]),
            'updated_by' => $this->whenLoaded('updatedBy', [
                'id' => $this->updatedBy?->id,
                'name' => $this->updatedBy?->name,
            ]),
        ];
    }
}
