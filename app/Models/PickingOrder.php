<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PickingOrder extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_POSTED = 'POSTED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'picking_order_number',
        'department_id',
        'warehouse_id',
        'status',
        'purpose',
        'picked_at',
        'created_by_user_id',
        'posted_at',
        'posted_by_user_id',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancel_reason',
        'remarks',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'picked_at' => 'datetime',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(PickingOrderLine::class);
    }

    public function statusHistories(): HasMany
    {
        return $this->hasMany(PickingOrderStatusHistory::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function postedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }
}
