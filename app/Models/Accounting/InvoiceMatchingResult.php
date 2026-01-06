<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceMatchingResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'supplier_invoice_id',
        'match_type',
        'overall_status',
        'total_quantity_variance',
        'total_price_variance',
        'total_amount_variance',
        'variance_percentage',
        'config_id',
        'quantity_tolerance_applied',
        'price_tolerance_applied',
        'amount_tolerance_applied',
        'requires_approval',
        'auto_approved',
        'rejection_reason',
        'matching_details',
        'matched_by_user_id',
        'matched_at',
    ];

    protected $casts = [
        'total_quantity_variance' => 'decimal:4',
        'total_price_variance' => 'decimal:2',
        'total_amount_variance' => 'decimal:2',
        'variance_percentage' => 'decimal:2',
        'quantity_tolerance_applied' => 'decimal:2',
        'price_tolerance_applied' => 'decimal:2',
        'amount_tolerance_applied' => 'decimal:2',
        'requires_approval' => 'boolean',
        'auto_approved' => 'boolean',
        'matching_details' => 'array',
        'matched_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function supplierInvoice(): BelongsTo
    {
        return $this->belongsTo(SupplierInvoice::class);
    }

    public function config(): BelongsTo
    {
        return $this->belongsTo(InvoiceMatchingConfig::class);
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by_user_id');
    }
}
