<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PurchaseOrder extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_SUBMITTED = 'SUBMITTED';
    public const STATUS_IN_APPROVAL = 'IN_APPROVAL';
    public const STATUS_APPROVED = 'APPROVED';
    public const STATUS_REJECTED = 'REJECTED';
    public const STATUS_SENT = 'SENT';
    public const STATUS_CLOSED = 'CLOSED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'po_number',
        'supplier_id',
        'status',
        'currency_code',
        'tax_rate',
        'subtotal_amount',
        'tax_amount',
        'total_amount',
        'supplier_snapshot',
        'tax_snapshot',
        'totals_snapshot',
        'submitted_at',
        'submitted_by_user_id',
        'approved_at',
        'approved_by_user_id',
        'sent_at',
        'sent_by_user_id',
        'closed_at',
        'closed_by_user_id',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancel_reason',
        // Payment fields
        'payment_status',
        'payment_term_days',
        'payment_due_date',
        'total_paid',
        'outstanding_amount',
    ];

    protected function casts(): array
    {
        return [
            'tax_rate' => 'decimal:6',
            'subtotal_amount' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'total_amount' => 'decimal:2',
            'supplier_snapshot' => 'array',
            'tax_snapshot' => 'array',
            'totals_snapshot' => 'array',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'sent_at' => 'datetime',
            'closed_at' => 'datetime',
            'cancelled_at' => 'datetime',
            // Payment casts
            'payment_due_date' => 'date',
            'total_paid' => 'decimal:2',
            'outstanding_amount' => 'decimal:2',
        ];
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function submittedBy()
    {
        return $this->belongsTo(User::class, 'submitted_by_user_id');
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    public function lines()
    {
        return $this->hasMany(PurchaseOrderLine::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(PurchaseOrderStatusHistory::class);
    }

    public function approvals()
    {
        return $this->morphMany(Approval::class, 'approvable');
    }

    public function purchaseRequests()
    {
        return $this->belongsToMany(PurchaseRequest::class, 'purchase_order_purchase_request')
            ->withTimestamps();
    }

    // Payment relationships
    public function payments()
    {
        return $this->hasMany(SupplierPayment::class);
    }

    public function paymentStatusHistories()
    {
        return $this->hasMany(PaymentStatusHistory::class);
    }

    public function goodsReceipts()
    {
        return $this->hasMany(GoodsReceipt::class);
    }

    /**
     * Check if PO is overdue for payment
     */
    public function isOverdue(): bool
    {
        return $this->payment_status !== 'PAID'
            && $this->payment_due_date
            && $this->payment_due_date->isPast();
    }

    /**
     * Update payment status based on payments
     */
    public function updatePaymentStatus(): void
    {
        $totalPaid = $this->payments()
            ->where('status', 'CONFIRMED')
            ->sum('payment_amount');

        $oldStatus = $this->payment_status;

        $this->total_paid = $totalPaid;
        $this->outstanding_amount = $this->total_amount - $totalPaid;

        if ($this->outstanding_amount <= 0) {
            $this->payment_status = 'PAID';
        } elseif ($totalPaid > 0) {
            $this->payment_status = 'PARTIAL';
        } elseif ($this->isOverdue()) {
            $this->payment_status = 'OVERDUE';
        } else {
            $this->payment_status = 'UNPAID';
        }

        $this->save();

        // Log status change if changed
        if ($oldStatus !== $this->payment_status) {
            PaymentStatusHistory::create([
                'purchase_order_id' => $this->id,
                'old_status' => $oldStatus,
                'new_status' => $this->payment_status,
                'changed_by_user_id' => auth()->id(),
                'notes' => "Payment status updated from {$oldStatus} to {$this->payment_status}",
            ]);
        }
    }

    /**
     * Get confirmed payments sum
     */
    public function getTotalPaidAttribute($value)
    {
        return $value ?? 0;
    }

    /**
     * Get outstanding amount
     */
    public function getOutstandingAmountAttribute($value)
    {
        return $value ?? $this->total_amount;
    }
}
