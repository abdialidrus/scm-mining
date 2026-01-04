<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemInventorySetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'warehouse_id',
        'reorder_point',
        'reorder_quantity',
        'min_stock',
        'max_stock',
        'lead_time_days',
        'safety_stock',
        'is_active',
        'notes',
    ];

    protected function casts(): array
    {
        return [
            'reorder_point' => 'decimal:4',
            'reorder_quantity' => 'decimal:4',
            'min_stock' => 'decimal:4',
            'max_stock' => 'decimal:4',
            'safety_stock' => 'decimal:4',
            'is_active' => 'boolean',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    /**
     * Get setting for specific item and warehouse (with fallback to default)
     */
    public static function getForItem(int $itemId, ?int $warehouseId = null): ?self
    {
        // Try to get warehouse-specific setting first
        if ($warehouseId) {
            $setting = self::where('item_id', $itemId)
                ->where('warehouse_id', $warehouseId)
                ->where('is_active', true)
                ->first();

            if ($setting) {
                return $setting;
            }
        }

        // Fallback to default setting (warehouse_id = NULL)
        return self::where('item_id', $itemId)
            ->whereNull('warehouse_id')
            ->where('is_active', true)
            ->first();
    }
}
