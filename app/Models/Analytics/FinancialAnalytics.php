<?php

namespace App\Models\Analytics;

use App\Models\PurchaseRequest;
use App\Models\PurchaseOrder;
use App\Models\GoodsReceipt;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class FinancialAnalytics
{
    /**
     * Get procurement spending summary
     */
    public static function getSpendingSummary(string $period = 'month'): array
    {
        $startDate = match ($period) {
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'quarter' => Carbon::now()->startOfQuarter(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::now()->startOfMonth(),
        };

        // Count PRs (no amounts, they're just requisitions)
        $totalPRsCount = DB::table('purchase_requests')
            ->where('created_at', '>=', $startDate)
            ->count();

        $totalPOs = DB::table('purchase_orders')
            ->leftJoin('purchase_order_lines', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->where('purchase_orders.created_at', '>=', $startDate)
            ->sum(DB::raw('purchase_order_lines.quantity * purchase_order_lines.unit_price'));

        $totalGRs = DB::table('goods_receipts')
            ->leftJoin('goods_receipt_lines', 'goods_receipts.id', '=', 'goods_receipt_lines.goods_receipt_id')
            ->where('goods_receipts.receipt_date', '>=', $startDate)
            ->sum(DB::raw('goods_receipt_lines.received_quantity * goods_receipt_lines.unit_price'));

        $committedSpend = DB::table('purchase_orders')
            ->leftJoin('purchase_order_lines', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->where('purchase_orders.created_at', '>=', $startDate)
            ->whereIn('purchase_orders.status', ['PENDING', 'APPROVED', 'PARTIALLY_RECEIVED'])
            ->sum(DB::raw('purchase_order_lines.quantity * purchase_order_lines.unit_price'));

        $actualSpend = DB::table('goods_receipts')
            ->leftJoin('goods_receipt_lines', 'goods_receipts.id', '=', 'goods_receipt_lines.goods_receipt_id')
            ->where('goods_receipts.receipt_date', '>=', $startDate)
            ->where('goods_receipts.status', 'COMPLETED')
            ->sum(DB::raw('goods_receipt_lines.received_quantity * goods_receipt_lines.unit_price'));

        return [
            'total_requisitions' => $totalPRsCount, // Count only, no amount
            'total_purchase_orders' => $totalPOs ?? 0,
            'total_goods_receipts' => $totalGRs ?? 0,
            'committed_spend' => $committedSpend ?? 0,
            'actual_spend' => $actualSpend ?? 0,
            'period' => $period,
            'start_date' => $startDate->toDateString(),
        ];
    }

    /**
     * Get monthly spending trend
     */
    public static function getMonthlySpendingTrend(int $months = 12): array
    {
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();

        $spending = DB::table('purchase_orders')
            ->select(
                DB::raw("DATE_TRUNC('month', purchase_orders.created_at) as month"),
                DB::raw('COALESCE(SUM(purchase_order_lines.quantity * purchase_order_lines.unit_price), 0) as total_amount'),
                DB::raw('COUNT(DISTINCT purchase_orders.id) as order_count')
            )
            ->leftJoin('purchase_order_lines', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->where('purchase_orders.created_at', '>=', $startDate)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        return [
            'months' => $spending->pluck('month')->map(fn($m) => Carbon::parse($m)->format('M Y')),
            'amounts' => $spending->pluck('total_amount'),
            'counts' => $spending->pluck('order_count'),
        ];
    }

    /**
     * Get budget vs actual comparison
     */
    public static function getBudgetVsActual(int $departmentId = null): array
    {
        // Calculate actual spending from POs (not PRs)
        // Join through pivot table: Departments -> PRs -> pivot -> POs -> PO lines
        $query = DB::table('departments')
            ->select(
                'departments.id',
                'departments.name',
                'departments.budget',
                DB::raw('COALESCE(SUM(purchase_order_lines.quantity * purchase_order_lines.unit_price), 0) as total_spent')
            )
            ->leftJoin('purchase_requests', 'departments.id', '=', 'purchase_requests.department_id')
            ->leftJoin('purchase_order_purchase_request', 'purchase_requests.id', '=', 'purchase_order_purchase_request.purchase_request_id')
            ->leftJoin('purchase_orders', 'purchase_order_purchase_request.purchase_order_id', '=', 'purchase_orders.id')
            ->leftJoin('purchase_order_lines', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->whereYear('purchase_orders.created_at', Carbon::now()->year)
            ->whereIn('purchase_orders.status', ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED', 'RECEIVED', 'CLOSED'])
            ->groupBy('departments.id', 'departments.name', 'departments.budget');

        if ($departmentId) {
            $query->where('departments.id', $departmentId);
        }

        $departments = $query->get();

        return [
            'departments' => $departments->pluck('name'),
            'budgets' => $departments->pluck('budget'),
            'actuals' => $departments->pluck('total_spent'),
            'variances' => $departments->map(fn($d) => [
                'amount' => $d->budget - $d->total_spent,
                'percentage' => $d->budget > 0
                    ? round((($d->budget - $d->total_spent) / $d->budget) * 100, 1)
                    : 0,
            ]),
        ];
    }
    /**
     * Get approval metrics
     */
    public static function getApprovalMetrics(int $months = 3): array
    {
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();

        // PR Approval metrics
        $prMetrics = PurchaseRequest::where('created_at', '>=', $startDate)
            ->whereNotNull('approved_at')
            ->selectRaw('
                COUNT(*) as total_approved,
                AVG(EXTRACT(EPOCH FROM (approved_at - created_at))/3600) as avg_hours_to_approve,
                MIN(EXTRACT(EPOCH FROM (approved_at - created_at))/3600) as min_hours,
                MAX(EXTRACT(EPOCH FROM (approved_at - created_at))/3600) as max_hours
            ')
            ->first();

        // PO Approval metrics
        $poMetrics = PurchaseOrder::where('created_at', '>=', $startDate)
            ->whereNotNull('approved_at')
            ->selectRaw('
                COUNT(*) as total_approved,
                AVG(EXTRACT(EPOCH FROM (approved_at - created_at))/3600) as avg_hours_to_approve
            ')
            ->first();

        $totalPRs = PurchaseRequest::where('created_at', '>=', $startDate)->count();
        $totalPOs = PurchaseOrder::where('created_at', '>=', $startDate)->count();

        return [
            'purchase_requests' => [
                'total' => $totalPRs,
                'approved' => $prMetrics->total_approved ?? 0,
                'approval_rate' => $totalPRs > 0
                    ? round((($prMetrics->total_approved ?? 0) / $totalPRs) * 100, 1)
                    : 0,
                'avg_hours_to_approve' => round($prMetrics->avg_hours_to_approve ?? 0, 1),
                'min_hours' => round($prMetrics->min_hours ?? 0, 1),
                'max_hours' => round($prMetrics->max_hours ?? 0, 1),
            ],
            'purchase_orders' => [
                'total' => $totalPOs,
                'approved' => $poMetrics->total_approved ?? 0,
                'approval_rate' => $totalPOs > 0
                    ? round((($poMetrics->total_approved ?? 0) / $totalPOs) * 100, 1)
                    : 0,
                'avg_hours_to_approve' => round($poMetrics->avg_hours_to_approve ?? 0, 1),
            ],
        ];
    }

    /**
     * Get payment status overview
     */
    public static function getPaymentStatusOverview(): array
    {
        $paymentStatus = DB::table('purchase_orders')
            ->select(
                'purchase_orders.payment_status',
                DB::raw('COUNT(DISTINCT purchase_orders.id) as count'),
                DB::raw('COALESCE(SUM(purchase_order_lines.quantity * purchase_order_lines.unit_price), 0) as total_amount')
            )
            ->leftJoin('purchase_order_lines', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->whereIn('purchase_orders.status', ['APPROVED', 'PARTIALLY_RECEIVED', 'RECEIVED', 'COMPLETED'])
            ->groupBy('purchase_orders.payment_status')
            ->get();

        $totalOutstanding = $paymentStatus
            ->whereIn('payment_status', ['PENDING', 'PARTIALLY_PAID'])
            ->sum('total_amount');

        return [
            'statuses' => $paymentStatus->map(fn($s) => [
                'name' => ucfirst(str_replace('_', ' ', $s->payment_status ?? 'Unknown')),
                'count' => $s->count,
                'amount' => $s->total_amount,
            ]),
            'total_outstanding' => $totalOutstanding,
        ];
    }

    /**
     * Get cost savings opportunities
     */
    public static function getCostSavingsOpportunities(): array
    {
        // Find items with price variations across suppliers
        $priceVariations = DB::table('purchase_order_lines')
            ->select(
                'items.code',
                'items.name',
                DB::raw('MIN(purchase_order_lines.unit_price) as min_price'),
                DB::raw('MAX(purchase_order_lines.unit_price) as max_price'),
                DB::raw('AVG(purchase_order_lines.unit_price) as avg_price'),
                DB::raw('COUNT(DISTINCT purchase_orders.supplier_id) as supplier_count')
            )
            ->join('purchase_orders', 'purchase_order_lines.purchase_order_id', '=', 'purchase_orders.id')
            ->join('items', 'purchase_order_lines.item_id', '=', 'items.id')
            ->where('purchase_orders.created_at', '>=', Carbon::now()->subMonths(6))
            ->groupBy('items.id', 'items.code', 'items.name')
            ->havingRaw('COUNT(DISTINCT purchase_orders.supplier_id) > 1')
            ->havingRaw('MAX(purchase_order_lines.unit_price) > MIN(purchase_order_lines.unit_price) * 1.1')
            ->orderByRaw('(MAX(purchase_order_lines.unit_price) - MIN(purchase_order_lines.unit_price)) DESC')
            ->limit(10)
            ->get();

        return [
            'items' => $priceVariations->map(fn($item) => [
                'code' => $item->code,
                'name' => $item->name,
                'min_price' => $item->min_price,
                'max_price' => $item->max_price,
                'avg_price' => round($item->avg_price, 2),
                'potential_savings_percent' => round((($item->max_price - $item->min_price) / $item->max_price) * 100, 1),
                'supplier_count' => $item->supplier_count,
            ]),
        ];
    }

    /**
     * Get spend by category
     */
    public static function getSpendByCategory(int $months = 12): array
    {
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();

        $spending = PurchaseOrder::select(
            'items.category',
            DB::raw('SUM(purchase_order_lines.quantity * purchase_order_lines.unit_price) as total_amount'),
            DB::raw('COUNT(DISTINCT purchase_orders.id) as order_count')
        )
            ->join('purchase_order_lines', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->join('items', 'purchase_order_lines.item_id', '=', 'items.id')
            ->where('purchase_orders.created_at', '>=', $startDate)
            ->groupBy('items.category')
            ->orderBy('total_amount', 'DESC')
            ->limit(10)
            ->get();

        return [
            'categories' => $spending->pluck('category')->map(fn($c) => ucfirst($c)),
            'amounts' => $spending->pluck('total_amount'),
            'counts' => $spending->pluck('order_count'),
        ];
    }

    /**
     * Get KPI summary for dashboard
     */
    public static function getKPISummary(): array
    {
        $thisMonth = Carbon::now()->startOfMonth();
        $lastMonth = Carbon::now()->subMonth()->startOfMonth();
        $lastMonthEnd = Carbon::now()->subMonth()->endOfMonth();

        // Current month metrics
        $currentSpend = DB::table('purchase_orders')
            ->leftJoin('purchase_order_lines', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->where('purchase_orders.created_at', '>=', $thisMonth)
            ->sum(DB::raw('purchase_order_lines.quantity * purchase_order_lines.unit_price'));

        $currentOrders = DB::table('purchase_orders')
            ->where('created_at', '>=', $thisMonth)
            ->count();

        $currentAvgOrderValue = $currentOrders > 0 ? $currentSpend / $currentOrders : 0;

        // Previous month metrics
        $previousSpend = DB::table('purchase_orders')
            ->leftJoin('purchase_order_lines', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->whereBetween('purchase_orders.created_at', [$lastMonth, $lastMonthEnd])
            ->sum(DB::raw('purchase_order_lines.quantity * purchase_order_lines.unit_price'));

        $previousOrders = DB::table('purchase_orders')
            ->whereBetween('created_at', [$lastMonth, $lastMonthEnd])
            ->count();

        // Calculate changes
        $spendChange = $previousSpend > 0
            ? (($currentSpend - $previousSpend) / $previousSpend) * 100
            : 0;

        $ordersChange = $previousOrders > 0
            ? (($currentOrders - $previousOrders) / $previousOrders) * 100
            : 0;

        // Pending approvals
        $pendingPRs = PurchaseRequest::where('status', 'PENDING_APPROVAL')->count();
        $pendingPOs = PurchaseOrder::where('status', 'PENDING')->count();

        return [
            'total_spend' => [
                'value' => $currentSpend ?? 0,
                'change' => round($spendChange, 1),
                'trend' => $spendChange >= 0 ? 'up' : 'down',
            ],
            'total_orders' => [
                'value' => $currentOrders,
                'change' => round($ordersChange, 1),
                'trend' => $ordersChange >= 0 ? 'up' : 'down',
            ],
            'avg_order_value' => [
                'value' => round($currentAvgOrderValue, 2),
            ],
            'pending_approvals' => [
                'prs' => $pendingPRs,
                'pos' => $pendingPOs,
                'total' => $pendingPRs + $pendingPOs,
            ],
        ];
    }
}
