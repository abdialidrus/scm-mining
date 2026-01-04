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
        $totalItems = StockBalance::distinct('item_id')->count('item_id');
        $totalQuantity = StockBalance::sum('qty_on_hand');

        // Note: stock_balances doesn't have unit_price, so we can't calculate total value
        // Total value would need to be calculated from other sources (e.g., latest PO prices)

        return [
            'total_items' => $totalItems,
            'total_quantity' => round($totalQuantity, 2),
            'total_value' => 0, // Not available in stock_balances
            'low_stock_items' => 0, // Not available - would need reorder_point from items or separate table
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
        $distribution = StockBalance::select(
            'warehouses.name as warehouse_name',
            DB::raw('COUNT(DISTINCT stock_balances.item_id) as item_count'),
            DB::raw('SUM(stock_balances.qty_on_hand) as total_quantity')
        )
            ->join('warehouse_locations', 'stock_balances.location_id', '=', 'warehouse_locations.id')
            ->join('warehouses', 'warehouse_locations.warehouse_id', '=', 'warehouses.id')
            ->groupBy('warehouses.id', 'warehouses.name')
            ->orderBy('total_quantity', 'DESC')
            ->get();

        return [
            'warehouses' => $distribution->pluck('warehouse_name'),
            'item_counts' => $distribution->pluck('item_count'),
            'quantities' => $distribution->pluck('total_quantity'),
            'values' => [], // Not available without unit prices
        ];
    }

    /**
     * Get top items by value
     */
    public static function getTopItemsByValue(int $limit = 10): array
    {
        // Since stock_balances doesn't have unit_price, we order by quantity instead
        $items = StockBalance::select(
            'items.sku as code',
            'items.name',
            DB::raw('SUM(stock_balances.qty_on_hand) as total_quantity')
        )
            ->join('items', 'stock_balances.item_id', '=', 'items.id')
            ->groupBy('items.id', 'items.sku', 'items.name')
            ->orderBy('total_quantity', 'DESC')
            ->limit($limit)
            ->get();

        return [
            'items' => $items->pluck('name'),
            'quantities' => $items->pluck('total_quantity'),
            'values' => [], // Not available without unit prices
        ];
    }

    /**
     * Get low stock items
     */
    public static function getLowStockItems(int $limit = 20): array
    {
        // Note: stock_balances doesn't have reorder_point
        // This would need to be implemented with a separate reorder_point configuration
        // For now, return empty as we can't determine low stock without reorder points

        return [
            'items' => [],
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
        // Note: Can't do ABC analysis without unit prices in stock_balances
        // Would need to join with purchase_order_lines or goods_receipt_lines for pricing
        // For now, return empty classification

        return [
            'A_items' => 0,
            'B_items' => 0,
            'C_items' => 0,
            'total_items' => 0,
        ];
    }
}
