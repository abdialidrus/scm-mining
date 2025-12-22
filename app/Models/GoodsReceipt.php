<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GoodsReceipt extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'DRAFT';
    public const STATUS_POSTED = 'POSTED';
    public const STATUS_CANCELLED = 'CANCELLED';

    protected $fillable = [
        'gr_number',
        'purchase_order_id',
        'warehouse_id',
        'status',
        'received_at',
        'remarks',
        'posted_at',
        'posted_by_user_id',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancel_reason',
        'purchase_order_snapshot',
        'warehouse_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'purchase_order_snapshot' => 'array',
            'warehouse_snapshot' => 'array',
        ];
    }

    public function purchaseOrder()
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function lines()
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }

    public function statusHistories()
    {
        return $this->hasMany(GoodsReceiptStatusHistory::class);
    }

    public function postedBy()
    {
        return $this->belongsTo(User::class, 'posted_by_user_id');
    }
}
