<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
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

    protected $appends = [
        'serial_numbers',
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

    /**
     * Get serial numbers for this picking order line
     *
     * @return Attribute<array|null, never>
     */
    protected function serialNumbers(): Attribute
    {
        return Attribute::make(
            get: function () {
                // Only get serial numbers for serialized items
                if (!$this->item || !$this->item->is_serialized) {
                    return null;
                }

                // Get serial numbers that were picked in this line
                return ItemSerialNumber::where('item_id', $this->item_id)
                    ->where('picking_order_line_id', $this->id)
                    ->pluck('serial_number')
                    ->toArray();
            }
        );
    }
}
