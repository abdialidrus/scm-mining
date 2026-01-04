<?php

namespace App\Repositories;

use App\Models\Analytics\ProcurementAnalytics;
use App\Models\Analytics\InventoryAnalytics;
use App\Models\Analytics\FinancialAnalytics;
use Illuminate\Support\Facades\Cache;

class DashboardRepository
{
    /**
     * Cache duration in seconds (15 minutes)
     */
    private const CACHE_TTL = 900;

    /**
     * Get complete dashboard data
     */
    public function getDashboardData(string $period = 'month'): array
    {
        $cacheKey = "dashboard_data_{$period}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($period) {
            return [
                'kpis' => $this->getKPIs(),
                'procurement_trend' => ProcurementAnalytics::getMonthlyTrend(6),
                'inventory_snapshot' => InventoryAnalytics::getInventorySnapshot(),
                'spending_summary' => FinancialAnalytics::getSpendingSummary($period),
                'period_comparison' => ProcurementAnalytics::getPeriodComparison($period),
            ];
        });
    }

    /**
     * Get KPI cards data
     */
    public function getKPIs(): array
    {
        return Cache::remember('dashboard_kpis', self::CACHE_TTL, function () {
            return FinancialAnalytics::getKPISummary();
        });
    }

    /**
     * Get procurement analytics
     */
    public function getProcurementAnalytics(int $months = 6): array
    {
        $cacheKey = "procurement_analytics_{$months}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($months) {
            return [
                'monthly_trend' => ProcurementAnalytics::getMonthlyTrend($months),
                'status_distribution' => ProcurementAnalytics::getStatusDistribution(),
                'department_spending' => ProcurementAnalytics::getDepartmentSpending($months),
                'top_suppliers' => ProcurementAnalytics::getTopSuppliers(10, $months),
                'cycle_time' => ProcurementAnalytics::getCycleTimeStats(),
            ];
        });
    }

    /**
     * Get inventory analytics
     */
    public function getInventoryAnalytics(int $months = 6): array
    {
        $cacheKey = "inventory_analytics_{$months}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($months) {
            return [
                'snapshot' => InventoryAnalytics::getInventorySnapshot(),
                'movement_trend' => InventoryAnalytics::getStockMovementTrend($months),
                'warehouse_distribution' => InventoryAnalytics::getWarehouseDistribution(),
                'top_items' => InventoryAnalytics::getTopItemsByValue(10),
                'low_stock' => InventoryAnalytics::getLowStockItems(20),
                'abc_analysis' => InventoryAnalytics::getABCAnalysis(),
            ];
        });
    }

    /**
     * Get financial analytics
     */
    public function getFinancialAnalytics(int $months = 12): array
    {
        $cacheKey = "financial_analytics_{$months}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($months) {
            return [
                'spending_trend' => FinancialAnalytics::getMonthlySpendingTrend($months),
                'budget_vs_actual' => FinancialAnalytics::getBudgetVsActual(),
                'approval_metrics' => FinancialAnalytics::getApprovalMetrics(3),
                'payment_status' => FinancialAnalytics::getPaymentStatusOverview(),
                'cost_savings' => FinancialAnalytics::getCostSavingsOpportunities(),
                'spend_by_category' => FinancialAnalytics::getSpendByCategory($months),
            ];
        });
    }

    /**
     * Get chart data by type
     */
    public function getChartData(string $chartType, array $params = []): array
    {
        $months = $params['months'] ?? 6;
        $cacheKey = "chart_{$chartType}_{$months}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($chartType, $months, $params) {
            return match ($chartType) {
                'procurement_trend' => ProcurementAnalytics::getMonthlyTrend($months),
                'status_distribution' => ProcurementAnalytics::getStatusDistribution(),
                'department_spending' => ProcurementAnalytics::getDepartmentSpending($months),
                'top_suppliers' => ProcurementAnalytics::getTopSuppliers($params['limit'] ?? 10, $months),
                'inventory_movement' => InventoryAnalytics::getStockMovementTrend($months),
                'warehouse_distribution' => InventoryAnalytics::getWarehouseDistribution(),
                'top_items' => InventoryAnalytics::getTopItemsByValue($params['limit'] ?? 10),
                'spending_trend' => FinancialAnalytics::getMonthlySpendingTrend($months),
                'budget_vs_actual' => FinancialAnalytics::getBudgetVsActual($params['department_id'] ?? null),
                'spend_by_category' => FinancialAnalytics::getSpendByCategory($months),
                default => [],
            };
        });
    }

    /**
     * Clear all dashboard caches
     */
    public function clearCache(): void
    {
        $patterns = [
            'dashboard_data_*',
            'dashboard_kpis',
            'procurement_analytics_*',
            'inventory_analytics_*',
            'financial_analytics_*',
            'chart_*',
        ];

        foreach ($patterns as $pattern) {
            Cache::forget($pattern);
        }
    }

    /**
     * Get goods receipt performance
     */
    public function getGoodsReceiptPerformance(int $months = 3): array
    {
        $cacheKey = "gr_performance_{$months}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($months) {
            return InventoryAnalytics::getGoodsReceiptPerformance($months);
        });
    }

    /**
     * Get put-away efficiency
     */
    public function getPutAwayEfficiency(int $months = 3): array
    {
        $cacheKey = "putaway_efficiency_{$months}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($months) {
            return InventoryAnalytics::getPutAwayEfficiency($months);
        });
    }
}
