<?php

namespace App\Http\Resources\Accounting;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierInvoiceLineResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Item Info
            'item' => [
                'id' => $this->item->id,
                'code' => $this->item->code,
                'name' => $this->item->name,
            ],
            'uom' => [
                'id' => $this->uom->id,
                'code' => $this->uom->code,
                'name' => $this->uom->name,
            ],

            // Invoiced Values
            'description' => $this->description,
            'invoiced_qty' => $this->invoiced_qty,
            'unit_price' => $this->unit_price,
            'line_total' => $this->line_total,
            'tax_amount' => $this->tax_amount,
            'discount_amount' => $this->discount_amount,

            // Expected Values (from PO/GR)
            'expected_qty' => $this->expected_qty,
            'expected_price' => $this->expected_price,
            'expected_amount' => $this->expected_amount,

            // Variances
            'quantity_variance' => $this->quantity_variance,
            'price_variance' => $this->price_variance,
            'amount_variance' => $this->amount_variance,
            'quantity_variance_percent' => $this->quantity_variance_percent,
            'price_variance_percent' => $this->price_variance_percent,
            'amount_variance_percent' => $this->amount_variance_percent,

            // Relationships
            'purchase_order_line' => $this->whenLoaded('purchaseOrderLine', [
                'id' => $this->purchaseOrderLine->id,
                'quantity' => $this->purchaseOrderLine->quantity,
                'unit_price' => $this->purchaseOrderLine->unit_price,
                'line_total' => $this->purchaseOrderLine->line_total,
            ]),
            'goods_receipt_line' => $this->whenLoaded('goodsReceiptLine', function () {
                return $this->goodsReceiptLine ? [
                    'id' => $this->goodsReceiptLine->id,
                    'received_qty' => $this->goodsReceiptLine->received_qty,
                    'goods_receipt' => [
                        'id' => $this->goodsReceiptLine->goodsReceipt->id,
                        'gr_number' => $this->goodsReceiptLine->goodsReceipt->gr_number,
                    ],
                ] : null;
            }),

            'notes' => $this->notes,

            // Helper flags
            'has_variance' => $this->hasVariance(),
            'line_subtotal' => $this->calculateLineTotal(),
        ];
    }
}
