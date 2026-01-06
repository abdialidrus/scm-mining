<?php

namespace App\Models\Accounting;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceMatchingConfig extends Model
{
    use HasFactory;

    protected $fillable = [
        'config_type',
        'reference_id',
        'quantity_tolerance_percent',
        'price_tolerance_percent',
        'amount_tolerance_percent',
        'allow_under_invoicing',
        'allow_over_invoicing',
        'require_approval_if_variance',
        'auto_approve_if_amount_below',
        'is_active',
        'notes',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected $casts = [
        'quantity_tolerance_percent' => 'decimal:2',
        'price_tolerance_percent' => 'decimal:2',
        'amount_tolerance_percent' => 'decimal:2',
        'allow_under_invoicing' => 'boolean',
        'allow_over_invoicing' => 'boolean',
        'require_approval_if_variance' => 'boolean',
        'auto_approve_if_amount_below' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Relationships
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by_user_id');
    }

    /**
     * Scopes
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeGlobal($query)
    {
        return $query->where('config_type', 'GLOBAL');
    }

    public function scopeForSupplier($query, int $supplierId)
    {
        return $query->where('config_type', 'SUPPLIER')
            ->where('reference_id', $supplierId);
    }

    /**
     * Get global config or create default if not exists
     */
    public static function getGlobalConfig(): self
    {
        return static::global()->active()->firstOrCreate(
            ['config_type' => 'GLOBAL'],
            [
                'quantity_tolerance_percent' => 0,
                'price_tolerance_percent' => 0,
                'amount_tolerance_percent' => 0,
                'allow_under_invoicing' => true,
                'allow_over_invoicing' => false,
                'require_approval_if_variance' => true,
                'is_active' => true,
                'notes' => 'Default global matching configuration - Zero tolerance',
            ]
        );
    }
}
