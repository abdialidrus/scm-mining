<?php

namespace App\Models\Accounting;

use App\Enums\Accounting\MatchingStatus;
use App\Models\GoodsReceiptLine;
use App\Models\Item;
use App\Models\PurchaseOrderLine;
use App\Models\Uom;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierInvoiceLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_invoice_id',
        'line_number',
        'item_id',
        'uom_id',
        'invoiced_qty',
        'unit_price',
        'discount_percent',
        'discount_amount',
        'tax_rate',
        'tax_amount',
        'line_total',
        'purchase_order_line_id',
        'goods_receipt_line_id',
        'matching_status',
        'expected_qty',
        'qty_variance',
        'qty_variance_percent',
        'expected_price',
        'price_variance',
        'price_variance_percent',
        'expected_amount',
        'amount_variance',
        'amount_variance_percent',
        'matching_notes',
        'remarks',
    ];

    protected $casts = [
        'invoiced_qty' => 'decimal:4',
        'unit_price' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'expected_qty' => 'decimal:4',
        'qty_variance' => 'decimal:4',
        'qty_variance_percent' => 'decimal:2',
        'expected_price' => 'decimal:2',
        'price_variance' => 'decimal:2',
        'price_variance_percent' => 'decimal:2',
        'expected_amount' => 'decimal:2',
        'amount_variance' => 'decimal:2',
        'amount_variance_percent' => 'decimal:2',
        'matching_status' => MatchingStatus::class,
    ];

    /**
     * Relationships
     */
    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function purchaseOrderLine(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderLine::class);
    }

    public function goodsReceiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class);
    }

    /**
     * Calculate line total based on qty, price, discount, and tax
     */
    public function calculateLineTotal(): float
    {
        $subtotal = $this->invoiced_qty * $this->unit_price;
        $afterDiscount = $subtotal - $this->discount_amount;
        $total = $afterDiscount + $this->tax_amount;

        return round($total, 2);
    }

    /**
     * Check if line has variance
     */
    public function hasVariance(): bool
    {
        // PENDING or MATCHED = no variance
        // QTY_VARIANCE, PRICE_VARIANCE, BOTH_VARIANCE, OVER_INVOICED = has variance
        return !in_array($this->matching_status, [MatchingStatus::MATCHED, MatchingStatus::PENDING]);
    }
}
