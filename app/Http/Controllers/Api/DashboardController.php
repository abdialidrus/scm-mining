<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Repositories\DashboardRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardRepository $dashboardRepository
    ) {}

    /**
     * Get dashboard summary data
     */
    public function index(Request $request): JsonResponse
    {
        $period = $request->input('period', 'month');

        $data = $this->dashboardRepository->getDashboardData($period);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get KPI cards data
     */
    public function kpis(): JsonResponse
    {
        $kpis = $this->dashboardRepository->getKPIs();

        return response()->json([
            'success' => true,
            'data' => $kpis,
        ]);
    }

    /**
     * Get procurement analytics
     */
    public function procurementAnalytics(Request $request): JsonResponse
    {
        $months = $request->input('months', 6);

        $data = $this->dashboardRepository->getProcurementAnalytics($months);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get inventory analytics
     */
    public function inventoryAnalytics(Request $request): JsonResponse
    {
        $months = $request->input('months', 6);

        $data = $this->dashboardRepository->getInventoryAnalytics($months);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get financial analytics
     */
    public function financialAnalytics(Request $request): JsonResponse
    {
        $months = $request->input('months', 12);

        $data = $this->dashboardRepository->getFinancialAnalytics($months);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get specific chart data
     */
    public function chartData(Request $request, string $chartType): JsonResponse
    {
        $params = $request->only(['months', 'limit', 'department_id']);

        $data = $this->dashboardRepository->getChartData($chartType, $params);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get goods receipt performance
     */
    public function goodsReceiptPerformance(Request $request): JsonResponse
    {
        $months = $request->input('months', 3);

        $data = $this->dashboardRepository->getGoodsReceiptPerformance($months);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Get put-away efficiency
     */
    public function putAwayEfficiency(Request $request): JsonResponse
    {
        $months = $request->input('months', 3);

        $data = $this->dashboardRepository->getPutAwayEfficiency($months);

        return response()->json([
            'success' => true,
            'data' => $data,
        ]);
    }

    /**
     * Clear dashboard cache
     */
    public function clearCache(): JsonResponse
    {

        $this->dashboardRepository->clearCache();

        return response()->json([
            'success' => true,
            'message' => 'Dashboard cache cleared successfully',
        ]);
    }
}
