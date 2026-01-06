<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InvoicePaymentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payment_number' => $this->payment_number,
            'payment_date' => $this->payment_date->format('Y-m-d'),
            'payment_amount' => $this->payment_amount,
            'payment_method' => $this->payment_method,

            // Bank Info
            'bank_name' => $this->bank_name,
            'bank_account' => $this->bank_account,
            'reference_number' => $this->reference_number,

            'notes' => $this->notes,

            // Payment Proof
            'payment_proof_path' => $this->payment_proof_path,
            'has_payment_proof' => !is_null($this->payment_proof_path),

            // Audit Info
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'created_by' => $this->whenLoaded('createdBy', [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
            ]),
        ];
    }
}
