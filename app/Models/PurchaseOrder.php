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
}
