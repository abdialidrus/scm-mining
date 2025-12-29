<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemSerialNumber extends Model
{
    use HasFactory;

    public const STATUS_AVAILABLE = 'AVAILABLE';
    public const STATUS_PICKED = 'PICKED';
    public const STATUS_DAMAGED = 'DAMAGED';
    public const STATUS_DISPOSED = 'DISPOSED';

    protected $fillable = [
        'item_id',
        'serial_number',
        'status',
        'current_location_id',
        'received_at',
        'goods_receipt_line_id',
        'picked_at',
        'picking_order_line_id',
        'remarks',
        'meta',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'picked_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function currentLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'current_location_id');
    }

    public function goodsReceiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class);
    }

    public function pickingOrderLine(): BelongsTo
    {
        return $this->belongsTo(PickingOrderLine::class);
    }
}
