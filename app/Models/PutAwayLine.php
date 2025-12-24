<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PutAwayLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'put_away_id',
        'goods_receipt_line_id',
        'item_id',
        'uom_id',
        'source_location_id',
        'destination_location_id',
        'qty',
        'remarks',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
    ];

    public function putAway(): BelongsTo
    {
        return $this->belongsTo(PutAway::class);
    }

    public function goodsReceiptLine(): BelongsTo
    {
        return $this->belongsTo(GoodsReceiptLine::class);
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

    public function destinationLocation(): BelongsTo
    {
        return $this->belongsTo(WarehouseLocation::class, 'destination_location_id');
    }
}
