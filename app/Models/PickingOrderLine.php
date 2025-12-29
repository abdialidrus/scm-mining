<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PickingOrderLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'picking_order_id',
        'item_id',
        'uom_id',
        'source_location_id',
        'qty',
        'remarks',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
    ];

    public function pickingOrder(): BelongsTo
    {
        return $this->belongsTo(PickingOrder::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function uom(): BelongsTo
    {
        return $this->belongsTo(Uom::class);
    }

    public function sourceLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'source_location_id');
    }
}
