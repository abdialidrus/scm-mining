<?php

namespace App\Services\Warehouse;

use App\Models\WarehouseLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LocationService
{
    /**
     * Create a new warehouse location
     *
     * @param array<string,mixed> $data
     * @return WarehouseLocation
     * @throws ValidationException
     */
    public function createLocation(array $data): WarehouseLocation
    {
        return DB::transaction(function () use ($data) {
            // If setting as default RECEIVING, check constraint
            if (
                isset($data['is_default']) &&
                $data['is_default'] === true &&
                $data['type'] === WarehouseLocation::TYPE_RECEIVING
            ) {
                $existingDefault = WarehouseLocation::query()
                    ->where('warehouse_id', $data['warehouse_id'])
                    ->where('type', WarehouseLocation::TYPE_RECEIVING)
                    ->where('is_default', true)
                    ->exists();

                if ($existingDefault) {
                    throw ValidationException::withMessages([
                        'is_default' => 'A default RECEIVING location already exists for this warehouse.',
                    ]);
                }
            }

            $location = WarehouseLocation::create([
                'warehouse_id' => $data['warehouse_id'],
                'parent_id' => $data['parent_id'] ?? null,
                'type' => $data['type'],
                'code' => $data['code'],
                'name' => $data['name'],
                'is_default' => $data['is_default'] ?? false,
                'is_active' => $data['is_active'] ?? true,
            ]);

            return $location;
        });
    }

    /**
     * Update an existing warehouse location
     *
     * @param WarehouseLocation $location
     * @param array<string,mixed> $data
     * @return WarehouseLocation
     * @throws ValidationException
     */
    public function updateLocation(WarehouseLocation $location, array $data): WarehouseLocation
    {
        return DB::transaction(function () use ($location, $data) {
            // If setting as default RECEIVING, check constraint
            if (
                isset($data['is_default']) &&
                $data['is_default'] === true &&
                ($data['type'] ?? $location->type) === WarehouseLocation::TYPE_RECEIVING
            ) {
                $existingDefault = WarehouseLocation::query()
                    ->where('warehouse_id', $data['warehouse_id'] ?? $location->warehouse_id)
                    ->where('type', WarehouseLocation::TYPE_RECEIVING)
                    ->where('is_default', true)
                    ->where('id', '!=', $location->id)
                    ->exists();

                if ($existingDefault) {
                    throw ValidationException::withMessages([
                        'is_default' => 'A default RECEIVING location already exists for this warehouse.',
                    ]);
                }
            }

            $location->fill($data);
            $location->save();

            return $location->fresh();
        });
    }

    /**
     * Soft delete a location by setting is_active = false
     *
     * @param WarehouseLocation $location
     * @return bool
     * @throws ValidationException
     */
    public function deactivateLocation(WarehouseLocation $location): bool
    {
        // Check if location has stock
        $hasStock = DB::table('stock_balances')
            ->where('location_id', $location->id)
            ->where('qty_on_hand', '>', 0)
            ->exists();

        if ($hasStock) {
            throw ValidationException::withMessages([
                'location' => 'Cannot deactivate location with stock. Please move stock to another location first.',
            ]);
        }

        // Check if location is default RECEIVING
        if ($location->is_default && $location->type === WarehouseLocation::TYPE_RECEIVING) {
            throw ValidationException::withMessages([
                'location' => 'Cannot deactivate default RECEIVING location. Please set another location as default first.',
            ]);
        }

        $location->is_active = false;
        return $location->save();
    }

    /**
     * Get stock summary for a location
     *
     * @param WarehouseLocation $location
     * @return array{items_count: int, total_quantity: float, total_value: float}
     */
    public function getStockSummary(WarehouseLocation $location): array
    {
        $summary = DB::table('stock_balances')
            ->where('location_id', $location->id)
            ->where('qty_on_hand', '>', 0)
            ->selectRaw('
                COUNT(DISTINCT item_id) as items_count,
                SUM(qty_on_hand) as total_quantity
            ')
            ->first();

        // Calculate total value (need to join with items and get prices)
        $totalValue = DB::table('stock_balances')
            ->where('stock_balances.location_id', $location->id)
            ->where('stock_balances.qty_on_hand', '>', 0)
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->leftJoin('goods_receipt_lines', function ($join) {
                $join->on('goods_receipt_lines.item_id', '=', 'stock_balances.item_id')
                    ->whereNotNull('goods_receipt_lines.id');
            })
            ->leftJoin('purchase_order_lines', 'goods_receipt_lines.purchase_order_line_id', '=', 'purchase_order_lines.id')
            ->selectRaw('
                stock_balances.item_id,
                stock_balances.qty_on_hand,
                AVG(purchase_order_lines.unit_price) as avg_unit_price
            ')
            ->groupBy('stock_balances.item_id', 'stock_balances.qty_on_hand')
            ->get()
            ->sum(function ($item) {
                return $item->qty_on_hand * ($item->avg_unit_price ?? 0);
            });

        return [
            'items_count' => (int) ($summary->items_count ?? 0),
            'total_quantity' => (float) ($summary->total_quantity ?? 0),
            'total_value' => (float) $totalValue,
        ];
    }

    /**
     * Get recent movements for a location
     *
     * @param WarehouseLocation $location
     * @param int $limit
     * @return \Illuminate\Support\Collection
     */
    public function getRecentMovements(WarehouseLocation $location, int $limit = 20)
    {
        return DB::table('stock_movements')
            ->where(function ($query) use ($location) {
                $query->where('source_location_id', $location->id)
                    ->orWhere('destination_location_id', $location->id);
            })
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->leftJoin('uoms', 'stock_movements.uom_id', '=', 'uoms.id')
            ->select([
                'stock_movements.id',
                'stock_movements.item_id',
                'items.sku',
                'items.name as item_name',
                'stock_movements.qty',
                'uoms.code as uom_code',
                'stock_movements.reference_type',
                'stock_movements.reference_id',
                'stock_movements.source_location_id',
                'stock_movements.destination_location_id',
                'stock_movements.movement_at',
            ])
            ->orderBy('stock_movements.movement_at', 'desc')
            ->limit($limit)
            ->get()
            ->map(function ($movement) use ($location) {
                // Determine if movement is IN or OUT for this location
                $isInbound = $movement->destination_location_id == $location->id;
                $movement->direction = $isInbound ? 'IN' : 'OUT';
                return $movement;
            });
    }

    /**
     * Get stock by item for a location
     */
    public function getStockByItem(WarehouseLocation $location)
    {
        return DB::table('stock_balances')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('uoms', 'stock_balances.uom_id', '=', 'uoms.id')
            ->leftJoin('item_categories', 'items.item_category_id', '=', 'item_categories.id')
            ->where('stock_balances.location_id', $location->id)
            ->where('stock_balances.qty_on_hand', '>', 0)
            ->select(
                'items.id as item_id',
                'items.sku as item_code',
                'items.name as item_name',
                'item_categories.name as category_name',
                'stock_balances.qty_on_hand',
                'uoms.code as uom_code',
                DB::raw('0 as total_value'),
                DB::raw('0 as unit_value')
            )
            ->orderBy('items.sku')
            ->get();
    }
}
