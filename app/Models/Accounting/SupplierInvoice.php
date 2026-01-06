<?php

namespace App\Models\Accounting;

use App\Enums\Accounting\InvoiceStatus;
use App\Enums\Accounting\MatchingStatus;
use App\Enums\Accounting\PaymentStatus;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class SupplierInvoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'internal_number',
        'supplier_id',
        'purchase_order_id',
        'invoice_date',
        'received_date',
        'due_date',
        'currency',
        'status',
        'matching_status',
        'matched_at',
        'matched_by_user_id',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'discount_amount',
        'other_charges',
        'total_amount',
        'payment_status',
        'paid_amount',
        'remaining_amount',
        'payment_terms',
        'requires_approval',
        'approval_status',
        'approved_by_user_id',
        'approved_at',
        'approval_notes',
        'invoice_file_path',
        'supplier_reference',
        'notes',
        'remarks',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'invoice_date' => 'date',
        'received_date' => 'date',
        'due_date' => 'date',
        'matched_at' => 'datetime',
        'approved_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'other_charges' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'remaining_amount' => 'decimal:2',
        'requires_approval' => 'boolean',
        'status' => InvoiceStatus::class,
        'matching_status' => MatchingStatus::class,
        'payment_status' => PaymentStatus::class,
    ];

    /**
     * Boot method
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($invoice) {
            if (empty($invoice->internal_number)) {
                $invoice->internal_number = static::generateInternalNumber();
            }

            // Set remaining amount sama dengan total amount
            if (is_null($invoice->remaining_amount)) {
                $invoice->remaining_amount = $invoice->total_amount;
            }
        });
    }

    /**
     * Generate internal invoice number: INV-YYYYMM-XXXX
     */
    public static function generateInternalNumber(): string
    {
        $prefix = 'INV-' . date('Ym') . '-';
        $lastInvoice = static::where('internal_number', 'like', $prefix . '%')
            ->orderBy('internal_number', 'desc')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->internal_number, -4);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $prefix . str_pad($newNumber, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Relationships
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(SupplierInvoiceLine::class);
    }

    public function matchingResult(): HasOne
    {
        return $this->hasOne(InvoiceMatchingResult::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    public function matchedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'matched_by_user_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by_user_id');
    }

    /**
     * Scopes
     */
    public function scopeUnpaid($query)
    {
        return $query->where('payment_status', PaymentStatus::UNPAID);
    }

    public function scopePending($query)
    {
        return $query->where('matching_status', MatchingStatus::PENDING);
    }

    public function scopeNeedApproval($query)
    {
        return $query->where('requires_approval', true)
            ->where('approval_status', 'PENDING');
    }

    public function scopeOverdue($query)
    {
        return $query->where('payment_status', PaymentStatus::OVERDUE)
            ->orWhere(function ($q) {
                $q->where('payment_status', PaymentStatus::UNPAID)
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', now());
            });
    }

    /**
     * Helpers
     */
    public function isEditable(): bool
    {
        return in_array($this->status, [InvoiceStatus::DRAFT, InvoiceStatus::SUBMITTED]);
    }

    public function canBeMatched(): bool
    {
        return in_array($this->status, [InvoiceStatus::SUBMITTED])
            && $this->matching_status !== null
            && $this->matching_status === MatchingStatus::PENDING;
    }

    public function isOverdue(): bool
    {
        if (!$this->due_date || $this->payment_status === PaymentStatus::PAID) {
            return false;
        }

        return $this->due_date < now() && $this->payment_status === PaymentStatus::UNPAID;
    }
}
