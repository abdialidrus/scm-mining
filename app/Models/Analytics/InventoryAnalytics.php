<?php

namespace App\Models\Analytics;

use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\GoodsReceipt;
use App\Models\PutAway;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InventoryAnalytics
{
    /**
     * Get current inventory snapshot
     */
    public static function getInventorySnapshot(): array
    {
        // Use the enhanced snapshot logic
        $enhanced = self::getEnhancedInventorySnapshot();

        return [
            'total_items' => $enhanced['total_items'],
            'total_quantity' => $enhanced['total_quantity'],
            'total_value' => $enhanced['total_value'],
            'low_stock_items' => $enhanced['low_stock_items'],
        ];
    }

    /**
     * Get stock movement trend
     */
    public static function getStockMovementTrend(int $months = 6): array
    {
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();

        // Stock movements don't have movement_type column
        // IN: destination_location_id IS NOT NULL (receiving into warehouse)
        // OUT: source_location_id IS NOT NULL (removing from warehouse)
        $movements = StockMovement::select(
            DB::raw("DATE_TRUNC('month', movement_at) as month"),
            DB::raw("CASE
                WHEN destination_location_id IS NOT NULL AND source_location_id IS NULL THEN 'IN'
                WHEN source_location_id IS NOT NULL AND destination_location_id IS NULL THEN 'OUT'
                ELSE 'TRANSFER'
            END as movement_type"),
            DB::raw('SUM(qty) as total_quantity')
        )
            ->where('movement_at', '>=', $startDate)
            ->groupBy('month', 'movement_type')
            ->orderBy('month')
            ->get()
            ->groupBy('month');

        $months = [];
        $inbound = [];
        $outbound = [];

        foreach ($movements as $month => $records) {
            $months[] = Carbon::parse($month)->format('M Y');
            $inbound[] = $records->where('movement_type', 'IN')->sum('total_quantity');
            $outbound[] = $records->where('movement_type', 'OUT')->sum('total_quantity');
        }

        return [
            'months' => $months,
            'inbound' => $inbound,
            'outbound' => $outbound,
        ];
    }

    /**
     * Get inventory by warehouse
     */
    public static function getWarehouseDistribution(): array
    {
        // Get valuation data
        $valuation = self::getStockValuation();

        // Group by warehouse
        $warehouseData = DB::table('stock_balances')
            ->select(
                'warehouses.id as warehouse_id',
                'warehouses.name as warehouse_name',
                DB::raw('COUNT(DISTINCT stock_balances.item_id) as item_count'),
                DB::raw('SUM(stock_balances.qty_on_hand) as total_quantity')
            )
            ->join('warehouse_locations', 'stock_balances.location_id', '=', 'warehouse_locations.id')
            ->join('warehouses', 'warehouse_locations.warehouse_id', '=', 'warehouses.id')
            ->groupBy('warehouses.id', 'warehouses.name')
            ->orderBy('total_quantity', 'DESC')
            ->get();

        // Calculate values per warehouse
        $warehouseValues = [];
        foreach ($warehouseData as $warehouse) {
            // Get aggregated items in this warehouse
            $itemsInWarehouse = DB::table('stock_balances')
                ->select('stock_balances.item_id', DB::raw('SUM(stock_balances.qty_on_hand) as total_qty'))
                ->join('warehouse_locations', 'stock_balances.location_id', '=', 'warehouse_locations.id')
                ->where('warehouse_locations.warehouse_id', $warehouse->warehouse_id)
                ->groupBy('stock_balances.item_id')
                ->get();

            $totalValue = 0;
            foreach ($itemsInWarehouse as $item) {
                // Find price from valuation
                $itemValuation = collect($valuation['items'])->firstWhere('item_id', $item->item_id);
                if ($itemValuation) {
                    $totalValue += $item->total_qty * $itemValuation['avg_unit_price'];
                }
            }
            $warehouseValues[] = round($totalValue, 2);
        }

        return [
            'warehouses' => $warehouseData->pluck('warehouse_name'),
            'item_counts' => $warehouseData->pluck('item_count'),
            'quantities' => $warehouseData->pluck('total_quantity')->map(fn($q) => number_format($q, 4, '.', '')),
            'values' => $warehouseValues,
        ];
    }

    /**
     * Get top items by value
     */
    public static function getTopItemsByValue(int $limit = 10): array
    {
        // Use stock valuation logic to get items with prices
        $valuation = self::getStockValuation();

        // Sort items by value (qty * price)
        $sortedItems = collect($valuation['items'])
            ->sortByDesc(function ($item) {
                return $item['quantity'] * $item['avg_unit_price'];
            })
            ->take($limit)
            ->values();

        return [
            'items' => $sortedItems->pluck('name'),
            'quantities' => $sortedItems->pluck('quantity')->map(fn($q) => number_format($q, 4, '.', '')),
            'values' => $sortedItems->map(function ($item) {
                return round($item['quantity'] * $item['avg_unit_price'], 2);
            }),
        ];
    }

    /**
     * Get low stock items
     */
    public static function getLowStockItems(int $limit = 20): array
    {
        // Use reorder recommendations logic
        $reorder = self::getReorderRecommendations(null, $limit);

        return [
            'items' => $reorder['items'],
        ];
    }

    /**
     * Get goods receipt performance
     */
    public static function getGoodsReceiptPerformance(int $months = 3): array
    {
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();

        $performance = GoodsReceipt::select(
            DB::raw("DATE_TRUNC('month', receipt_date) as month"),
            DB::raw('COUNT(*) as receipt_count'),
            DB::raw('AVG(EXTRACT(EPOCH FROM (completed_at - receipt_date))/3600) as avg_hours_to_complete')
        )
            ->where('receipt_date', '>=', $startDate)
            ->whereNotNull('completed_at')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'months' => $performance->pluck('month')->map(fn($m) => Carbon::parse($m)->format('M Y')),
            'counts' => $performance->pluck('receipt_count'),
            'avg_hours' => $performance->pluck('avg_hours_to_complete')->map(fn($h) => round($h, 1)),
        ];
    }

    /**
     * Get put-away efficiency
     */
    public static function getPutAwayEfficiency(int $months = 3): array
    {
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();

        $efficiency = PutAway::select(
            DB::raw("DATE_TRUNC('month', created_at) as month"),
            DB::raw('COUNT(*) as putaway_count'),
            DB::raw('AVG(EXTRACT(EPOCH FROM (completed_at - created_at))/3600) as avg_hours_to_complete')
        )
            ->where('created_at', '>=', $startDate)
            ->whereNotNull('completed_at')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'months' => $efficiency->pluck('month')->map(fn($m) => Carbon::parse($m)->format('M Y')),
            'counts' => $efficiency->pluck('putaway_count'),
            'avg_hours' => $efficiency->pluck('avg_hours_to_complete')->map(fn($h) => round($h, 1)),
        ];
    }

    /**
     * Get ABC analysis (Pareto)
     */
    public static function getABCAnalysis(): array
    {
        // Use stock valuation to calculate ABC classification
        $valuation = self::getStockValuation();

        if (empty($valuation['items'])) {
            return [
                'A_items' => 0,
                'B_items' => 0,
                'C_items' => 0,
                'total_items' => 0,
            ];
        }

        // Calculate value for each item and sort
        $itemsWithValue = collect($valuation['items'])->map(function ($item) {
            return [
                'item_id' => $item['item_id'],
                'value' => $item['quantity'] * $item['avg_unit_price'],
            ];
        })->sortByDesc('value')->values();

        $totalValue = $itemsWithValue->sum('value');
        $totalItems = $itemsWithValue->count();

        // ABC Classification:
        // A: Top items that contribute 80% of value (usually 20% of items)
        // B: Next items that contribute 15% of value (usually 30% of items)
        // C: Remaining items that contribute 5% of value (usually 50% of items)

        $aCount = 0;
        $bCount = 0;
        $cCount = 0;
        $cumulativeValue = 0;

        foreach ($itemsWithValue as $item) {
            $cumulativeValue += $item['value'];
            $percentage = ($cumulativeValue / $totalValue) * 100;

            if ($percentage <= 80) {
                $aCount++;
            } elseif ($percentage <= 95) {
                $bCount++;
            } else {
                $cCount++;
            }
        }

        return [
            'A_items' => $aCount,
            'B_items' => $bCount,
            'C_items' => $cCount,
            'total_items' => $totalItems,
        ];
    }

    /**
     * Get stock valuation using FIFO method
     * Calculate from latest goods_receipt_lines or fallback to purchase_order_lines
     */
    public static function getStockValuation(): array
    {
        // Get stock balances with latest prices from PO via GR (FIFO - First In First Out)
        // Use simpler approach: just get average price without ordering
        $valuation = DB::table('stock_balances')
            ->select(
                'stock_balances.item_id',
                'items.sku',
                'items.name',
                DB::raw('SUM(stock_balances.qty_on_hand) as total_qty'),
                DB::raw('COALESCE(
                    (SELECT AVG(po.unit_price)
                     FROM goods_receipt_lines gr
                     INNER JOIN purchase_order_lines po ON gr.purchase_order_line_id = po.id
                     WHERE gr.item_id = stock_balances.item_id
                     LIMIT 10),
                    (SELECT AVG(po.unit_price)
                     FROM purchase_order_lines po
                     WHERE po.item_id = stock_balances.item_id
                     LIMIT 10),
                    0
                ) as avg_unit_price')
            )
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->groupBy('stock_balances.item_id', 'items.sku', 'items.name')
            ->having(DB::raw('SUM(stock_balances.qty_on_hand)'), '>', 0)
            ->get();

        $totalValue = $valuation->sum(function ($item) {
            return $item->total_qty * $item->avg_unit_price;
        });

        return [
            'total_value' => round($totalValue, 2),
            'total_items' => $valuation->count(),
            'total_quantity' => round($valuation->sum('total_qty'), 2),
            'items' => $valuation->map(function ($item) {
                return [
                    'item_id' => $item->item_id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'quantity' => round($item->total_qty, 2),
                    'avg_unit_price' => round($item->avg_unit_price, 2),
                    'total_value' => round($item->total_qty * $item->avg_unit_price, 2),
                ];
            })->toArray(),
        ];
    }

    /**
     * Get top moving items (highest movement frequency)
     */
    public static function getTopMovingItems(int $days = 30, int $limit = 10): array
    {
        $startDate = Carbon::now()->subDays($days)->startOfDay();

        $movements = DB::table('stock_movements')
            ->select(
                'items.id as item_id',
                'items.sku',
                'items.name',
                DB::raw('COUNT(*) as movement_count'),
                DB::raw('SUM(ABS(qty)) as total_qty_moved')
            )
            ->join('items', 'stock_movements.item_id', '=', 'items.id')
            ->where('movement_at', '>=', $startDate)
            ->groupBy('items.id', 'items.sku', 'items.name')
            ->orderBy('movement_count', 'DESC')
            ->limit($limit)
            ->get();

        return [
            'items' => $movements->pluck('name'),
            'skus' => $movements->pluck('sku'),
            'movement_counts' => $movements->pluck('movement_count'),
            'total_qty_moved' => $movements->pluck('total_qty_moved')->map(fn($q) => round($q, 2)),
        ];
    }

    /**
     * Get stock aging analysis
     * Group items by days since last movement
     */
    public static function getStockAgingAnalysis(): array
    {
        // Use WITH clause to pre-calculate the aging date
        $aging = DB::select("
            WITH item_ages AS (
                SELECT
                    sb.item_id,
                    sb.qty_on_hand,
                    COALESCE(lm.movement_at, sb.created_at) as reference_date
                FROM stock_balances sb
                LEFT JOIN (
                    SELECT item_id, MAX(movement_at) as movement_at
                    FROM stock_movements
                    GROUP BY item_id
                ) lm ON sb.item_id = lm.item_id
                WHERE sb.qty_on_hand > 0
            )
            SELECT
                CASE
                    WHEN EXTRACT(DAY FROM (NOW() - reference_date)) <= 30 THEN '0-30 days'
                    WHEN EXTRACT(DAY FROM (NOW() - reference_date)) <= 60 THEN '31-60 days'
                    WHEN EXTRACT(DAY FROM (NOW() - reference_date)) <= 90 THEN '61-90 days'
                    ELSE '90+ days'
                END as age_bucket,
                COUNT(DISTINCT item_id) as item_count,
                SUM(qty_on_hand) as total_qty
            FROM item_ages
            GROUP BY
                CASE
                    WHEN EXTRACT(DAY FROM (NOW() - reference_date)) <= 30 THEN '0-30 days'
                    WHEN EXTRACT(DAY FROM (NOW() - reference_date)) <= 60 THEN '31-60 days'
                    WHEN EXTRACT(DAY FROM (NOW() - reference_date)) <= 90 THEN '61-90 days'
                    ELSE '90+ days'
                END
            ORDER BY
                CASE
                    WHEN CASE
                        WHEN EXTRACT(DAY FROM (NOW() - reference_date)) <= 30 THEN '0-30 days'
                        WHEN EXTRACT(DAY FROM (NOW() - reference_date)) <= 60 THEN '31-60 days'
                        WHEN EXTRACT(DAY FROM (NOW() - reference_date)) <= 90 THEN '61-90 days'
                        ELSE '90+ days'
                    END = '0-30 days' THEN 1
                    WHEN CASE
                        WHEN EXTRACT(DAY FROM (NOW() - reference_date)) <= 30 THEN '0-30 days'
                        WHEN EXTRACT(DAY FROM (NOW() - reference_date)) <= 60 THEN '31-60 days'
                        WHEN EXTRACT(DAY FROM (NOW() - reference_date)) <= 90 THEN '61-90 days'
                        ELSE '90+ days'
                    END = '31-60 days' THEN 2
                    WHEN CASE
                        WHEN EXTRACT(DAY FROM (NOW() - reference_date)) <= 30 THEN '0-30 days'
                        WHEN EXTRACT(DAY FROM (NOW() - reference_date)) <= 60 THEN '31-60 days'
                        WHEN EXTRACT(DAY FROM (NOW() - reference_date)) <= 90 THEN '61-90 days'
                        ELSE '90+ days'
                    END = '61-90 days' THEN 3
                    ELSE 4
                END
        ");

        return [
            'buckets' => collect($aging)->pluck('age_bucket'),
            'item_counts' => collect($aging)->pluck('item_count'),
            'quantities' => collect($aging)->pluck('total_qty')->map(fn($q) => round((float)$q, 2)),
        ];
    }

    /**
     * Get reorder recommendations based on settings and current stock
     */
    public static function getReorderRecommendations(?int $warehouseId = null, int $limit = 50): array
    {
        $query = DB::table('stock_balances')
            ->select(
                'items.id as item_id',
                'items.sku',
                'items.name',
                'warehouses.name as warehouse_name',
                DB::raw('SUM(stock_balances.qty_on_hand) as current_stock'),
                'settings.reorder_point',
                'settings.reorder_quantity',
                'settings.lead_time_days',
                DB::raw('(settings.reorder_point - SUM(stock_balances.qty_on_hand)) as shortage')
            )
            ->join('warehouse_locations', 'stock_balances.location_id', '=', 'warehouse_locations.id')
            ->join('warehouses', 'warehouse_locations.warehouse_id', '=', 'warehouses.id')
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->join('item_inventory_settings as settings', function ($join) {
                $join->on('items.id', '=', 'settings.item_id')
                    ->where('settings.is_active', '=', true);
            })
            ->where('settings.reorder_point', '>', 0)
            ->groupBy('items.id', 'items.sku', 'items.name', 'warehouses.id', 'warehouses.name', 'settings.reorder_point', 'settings.reorder_quantity', 'settings.lead_time_days')
            ->havingRaw('SUM(stock_balances.qty_on_hand) <= settings.reorder_point')
            ->orderByRaw('(settings.reorder_point - SUM(stock_balances.qty_on_hand)) DESC')
            ->limit($limit);

        if ($warehouseId) {
            $query->where('warehouses.id', $warehouseId);
        }

        $recommendations = $query->get();

        return [
            'items' => $recommendations->map(function ($item) {
                return [
                    'item_id' => $item->item_id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'warehouse' => $item->warehouse_name,
                    'current_stock' => round($item->current_stock, 2),
                    'reorder_point' => round($item->reorder_point, 2),
                    'reorder_quantity' => round($item->reorder_quantity, 2),
                    'shortage' => round($item->shortage, 2),
                    'lead_time_days' => $item->lead_time_days,
                    'stock_level_percent' => $item->reorder_point > 0
                        ? round(($item->current_stock / $item->reorder_point) * 100, 1)
                        : 0,
                ];
            }),
            'total_items' => $recommendations->count(),
        ];
    }

    /**
     * Get stock turnover rate
     * Turnover = Cost of Goods Sold / Average Inventory Value
     */
    public static function getStockTurnoverRate(int $months = 12): array
    {
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();

        // Calculate COGS from goods receipts (get price from PO)
        $cogs = DB::table('goods_receipt_lines')
            ->join('goods_receipts', 'goods_receipt_lines.goods_receipt_id', '=', 'goods_receipts.id')
            ->join('purchase_order_lines', 'goods_receipt_lines.purchase_order_line_id', '=', 'purchase_order_lines.id')
            ->where('goods_receipts.received_at', '>=', $startDate)
            ->whereNotNull('goods_receipts.received_at')
            ->sum(DB::raw('goods_receipt_lines.received_quantity * purchase_order_lines.unit_price'));

        // Calculate average inventory value using simpler query
        $result = DB::select("
            SELECT AVG(calculated_value) as avg_value
            FROM (
                SELECT
                    sb.item_id,
                    sb.qty_on_hand * COALESCE(
                        (SELECT AVG(po.unit_price)
                         FROM goods_receipt_lines gr
                         INNER JOIN purchase_order_lines po ON gr.purchase_order_line_id = po.id
                         WHERE gr.item_id = sb.item_id
                         LIMIT 10),
                        0
                    ) as calculated_value
                FROM stock_balances sb
                WHERE sb.qty_on_hand > 0
            ) as inventory_values
        ");

        $avgInventoryValue = $result[0]->avg_value ?? 0;

        $turnoverRate = $avgInventoryValue > 0 ? $cogs / $avgInventoryValue : 0;

        return [
            'turnover_rate' => round($turnoverRate, 2),
            'cogs' => round($cogs, 2),
            'avg_inventory_value' => round($avgInventoryValue, 2),
            'period_months' => $months,
        ];
    }

    /**
     * Get dead stock analysis (slow-moving/non-moving)
     */
    public static function getDeadStockAnalysis(int $days = 90, int $limit = 50): array
    {
        $cutoffDate = Carbon::now()->subDays($days);

        $deadStock = DB::table('stock_balances')
            ->select(
                'items.id as item_id',
                'items.sku',
                'items.name',
                DB::raw('SUM(stock_balances.qty_on_hand) as current_stock'),
                DB::raw('MAX(stock_movements.movement_at) as last_movement_date'),
                DB::raw('EXTRACT(DAY FROM (NOW() - COALESCE(MAX(stock_movements.movement_at), stock_balances.created_at))) as days_since_movement'),
                DB::raw('SUM(stock_balances.qty_on_hand) * COALESCE(
                    (SELECT AVG(po.unit_price)
                     FROM goods_receipt_lines gr
                     INNER JOIN purchase_order_lines po ON gr.purchase_order_line_id = po.id
                     WHERE gr.item_id = stock_balances.item_id
                     ORDER BY gr.created_at DESC
                     LIMIT 10),
                    0
                ) as estimated_value')
            )
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->leftJoin('stock_movements', 'stock_balances.item_id', '=', 'stock_movements.item_id')
            ->where('stock_balances.qty_on_hand', '>', 0)
            ->groupBy('items.id', 'items.sku', 'items.name', 'stock_balances.created_at')
            ->havingRaw('COALESCE(MAX(stock_movements.movement_at), stock_balances.created_at) <= ?', [$cutoffDate])
            ->orderByRaw('EXTRACT(DAY FROM (NOW() - COALESCE(MAX(stock_movements.movement_at), stock_balances.created_at))) DESC')
            ->limit($limit)
            ->get();

        $totalValue = $deadStock->sum('estimated_value');

        return [
            'items' => $deadStock->map(function ($item) {
                return [
                    'item_id' => $item->item_id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'current_stock' => round($item->current_stock, 2),
                    'last_movement_date' => $item->last_movement_date,
                    'days_since_movement' => (int) $item->days_since_movement,
                    'estimated_value' => round($item->estimated_value, 2),
                ];
            }),
            'total_items' => $deadStock->count(),
            'total_value' => round($totalValue, 2),
            'period_days' => $days,
        ];
    }

    /**
     * Get enhanced inventory snapshot with valuation
     */
    public static function getEnhancedInventorySnapshot(): array
    {
        $valuation = self::getStockValuation();
        $reorderAlerts = self::getReorderRecommendations(null, 100);

        return [
            'total_items' => $valuation['total_items'],
            'total_quantity' => $valuation['total_quantity'],
            'total_value' => $valuation['total_value'],
            'low_stock_items' => $reorderAlerts['total_items'],
        ];
    }
}
