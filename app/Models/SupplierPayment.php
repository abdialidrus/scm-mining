<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupplierPayment extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_number',
        'purchase_order_id',
        'supplier_invoice_number',
        'supplier_invoice_date',
        'supplier_invoice_amount',
        'supplier_invoice_file_path',
        'payment_date',
        'payment_amount',
        'payment_method',
        'payment_reference',
        'payment_proof_file_path',
        'bank_account_from',
        'bank_account_to',
        'status',
        'notes',
        'approved_by_user_id',
        'approved_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'supplier_invoice_date' => 'date',
        'payment_date' => 'date',
        'supplier_invoice_amount' => 'decimal:2',
        'payment_amount' => 'decimal:2',
        'approved_at' => 'datetime',
    ];

    /**
     * Relationships
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Generate payment number: PAY-YYYYMM-XXXX
     */
    public static function generatePaymentNumber(): string
    {
        $prefix = 'PAY-' . date('Ym') . '-';

        $last = static::where('payment_number', 'like', $prefix . '%')
            ->latest('id')
            ->first();

        $number = $last ? intval(substr($last->payment_number, -4)) + 1 : 1;

        return $prefix . str_pad($number, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Check if payment is confirmed
     */
    public function isConfirmed(): bool
    {
        return $this->status === 'CONFIRMED';
    }

    /**
     * Check if payment is draft
     */
    public function isDraft(): bool
    {
        return $this->status === 'DRAFT';
    }

    /**
     * Check if payment is cancelled
     */
    public function isCancelled(): bool
    {
        return $this->status === 'CANCELLED';
    }

    /**
     * Scope for confirmed payments only
     */
    public function scopeConfirmed($query)
    {
        return $query->where('status', 'CONFIRMED');
    }

    /**
     * Scope for draft payments
     */
    public function scopeDraft($query)
    {
        return $query->where('status', 'DRAFT');
    }
}
