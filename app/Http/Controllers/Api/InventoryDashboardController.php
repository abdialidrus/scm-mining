<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Analytics\InventoryAnalytics;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class InventoryDashboardController extends Controller
{
    /**
     * Get complete inventory dashboard data
     */
    public function index(Request $request): JsonResponse
    {
        $cacheKey = 'inventory_dashboard_' . $request->get('warehouse_id', 'all');

        $data = Cache::remember($cacheKey, 900, function () use ($request) {
            $warehouseId = $request->get('warehouse_id');

            return [
                'kpis' => InventoryAnalytics::getEnhancedInventorySnapshot(),
                'movement_trend' => InventoryAnalytics::getStockMovementTrend((int) $request->get('months', 6)),
                'warehouse_distribution' => InventoryAnalytics::getWarehouseDistribution(),
                'top_moving_items' => InventoryAnalytics::getTopMovingItems((int) $request->get('days', 30), 10),
                'stock_aging' => InventoryAnalytics::getStockAgingAnalysis(),
                'reorder_alerts' => InventoryAnalytics::getReorderRecommendations($warehouseId, 20),
                'goods_receipt_performance' => InventoryAnalytics::getGoodsReceiptPerformance(3),
                'putaway_efficiency' => InventoryAnalytics::getPutAwayEfficiency(3),
                'turnover_rate' => InventoryAnalytics::getStockTurnoverRate(12),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get inventory KPIs
     */
    public function kpis(): JsonResponse
    {
        $data = Cache::remember('inventory_kpis', 900, function () {
            return InventoryAnalytics::getEnhancedInventorySnapshot();
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get stock valuation details
     */
    public function stockValuation(): JsonResponse
    {
        $data = Cache::remember('inventory_stock_valuation', 900, function () {
            return InventoryAnalytics::getStockValuation();
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get movement analysis
     */
    public function movementAnalysis(Request $request): JsonResponse
    {
        $months = (int) $request->get('months', 6);
        $cacheKey = 'inventory_movement_' . $months;

        $data = Cache::remember($cacheKey, 900, function () use ($months) {
            return InventoryAnalytics::getStockMovementTrend($months);
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get warehouse comparison
     */
    public function warehouseComparison(): JsonResponse
    {
        $data = Cache::remember('inventory_warehouse_comparison', 900, function () {
            return InventoryAnalytics::getWarehouseDistribution();
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get top moving items
     */
    public function topMovingItems(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 30);
        $limit = (int) $request->get('limit', 10);
        $cacheKey = 'inventory_top_moving_' . $days . '_' . $limit;

        $data = Cache::remember($cacheKey, 900, function () use ($days, $limit) {
            return InventoryAnalytics::getTopMovingItems($days, $limit);
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get stock aging analysis
     */
    public function stockAging(): JsonResponse
    {
        $data = Cache::remember('inventory_stock_aging', 900, function () {
            return InventoryAnalytics::getStockAgingAnalysis();
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get reorder recommendations
     */
    public function reorderRecommendations(Request $request): JsonResponse
    {
        $warehouseId = $request->get('warehouse_id');
        $limit = (int) $request->get('limit', 50);
        $cacheKey = 'inventory_reorder_' . ($warehouseId ?? 'all') . '_' . $limit;

        $data = Cache::remember($cacheKey, 300, function () use ($warehouseId, $limit) {
            return InventoryAnalytics::getReorderRecommendations($warehouseId, $limit);
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get dead stock analysis
     */
    public function deadStock(Request $request): JsonResponse
    {
        $days = (int) $request->get('days', 90);
        $limit = (int) $request->get('limit', 50);
        $cacheKey = 'inventory_dead_stock_' . $days . '_' . $limit;

        $data = Cache::remember($cacheKey, 1800, function () use ($days, $limit) {
            return InventoryAnalytics::getDeadStockAnalysis($days, $limit);
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get stock turnover rate
     */
    public function turnoverRate(Request $request): JsonResponse
    {
        $months = (int) $request->get('months', 12);
        $cacheKey = 'inventory_turnover_' . $months;

        $data = Cache::remember($cacheKey, 1800, function () use ($months) {
            return InventoryAnalytics::getStockTurnoverRate($months);
        });

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Clear inventory dashboard cache
     */
    public function clearCache(): JsonResponse
    {
        Cache::flush(); // Or use specific tag if using tagged cache

        return response()->json([
            'success' => true,
            'message' => 'Inventory dashboard cache cleared successfully',
        ]);
    }
}
