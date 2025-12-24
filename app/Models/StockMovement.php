<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    use HasFactory;

    public const REF_GOODS_RECEIPT = 'GOODS_RECEIPT';
    public const REF_PUT_AWAY = 'PUT_AWAY';
    public const REF_ADJUSTMENT = 'ADJUSTMENT';

    protected $fillable = [
        'item_id',
        'uom_id',
        'source_location_id',
        'destination_location_id',
        'qty',
        'reference_type',
        'reference_id',
        'created_by',
        'movement_at',
        'meta',
    ];

    protected $casts = [
        'qty' => 'decimal:4',
        'movement_at' => 'datetime',
        'meta' => 'array',
    ];

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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
