<?php

namespace App\Models\Analytics;

use App\Models\PurchaseRequest;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ProcurementAnalytics
{
    /**
     * Get monthly procurement trend for the last N months
     */
    public static function getMonthlyTrend(int $months = 6): array
    {
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();

        // Get PR counts
        $prs = DB::table('purchase_requests')
            ->select(
                DB::raw("DATE_TRUNC('month', created_at) as month"),
                DB::raw('COUNT(*) as count')
            )
            ->where('created_at', '>=', $startDate)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Get PO amounts (actual spending)
        $pos = DB::table('purchase_orders')
            ->select(
                DB::raw("DATE_TRUNC('month', purchase_orders.created_at) as month"),
                DB::raw('COALESCE(SUM(purchase_order_lines.quantity * purchase_order_lines.unit_price), 0) as total_amount')
            )
            ->leftJoin('purchase_order_lines', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->where('purchase_orders.created_at', '>=', $startDate)
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        // Merge data by month
        $months = $prs->pluck('month')->merge($pos->pluck('month'))->unique()->sort();
        $monthsFormatted = $months->map(fn($m) => Carbon::parse($m)->format('M Y'));

        $counts = [];
        $amounts = [];

        foreach ($months as $month) {
            $pr = $prs->firstWhere('month', $month);
            $po = $pos->firstWhere('month', $month);

            $counts[] = $pr->count ?? 0;
            $amounts[] = $po->total_amount ?? 0;
        }

        return [
            'months' => $monthsFormatted->values(),
            'counts' => $counts,
            'amounts' => $amounts,
            'averages' => array_map(fn($amount, $count) => $count > 0 ? $amount / $count : 0, $amounts, $counts),
        ];
    }

    /**
     * Get status distribution for PRs and POs
     */
    public static function getStatusDistribution(): array
    {
        $prStatuses = PurchaseRequest::select('status', DB::raw('COUNT(*) as count'))
            ->groupBy('status')
            ->get()
            ->map(fn($item) => [
                'name' => ucfirst(str_replace('_', ' ', $item->status)),
                'value' => $item->count,
                'type' => 'PR',
            ]);

        return $prStatuses->toArray();
    }

    /**
     * Get spending by department
     */
    public static function getDepartmentSpending(int $months = 12): array
    {
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();

        // Calculate spending from POs (not PRs, since PRs don't have prices)
        // Join through pivot table: POs -> pivot -> PRs -> Departments
        $spending = DB::table('purchase_orders')
            ->select(
                'departments.id as department_id',
                'departments.name as department_name',
                DB::raw('COUNT(DISTINCT purchase_orders.id) as po_count'),
                DB::raw('COALESCE(SUM(purchase_order_lines.quantity * purchase_order_lines.unit_price), 0) as total_spent')
            )
            ->join('purchase_order_purchase_request', 'purchase_orders.id', '=', 'purchase_order_purchase_request.purchase_order_id')
            ->join('purchase_requests', 'purchase_order_purchase_request.purchase_request_id', '=', 'purchase_requests.id')
            ->join('departments', 'purchase_requests.department_id', '=', 'departments.id')
            ->leftJoin('purchase_order_lines', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->where('purchase_orders.created_at', '>=', $startDate)
            ->whereIn('purchase_orders.status', ['APPROVED', 'SENT', 'PARTIALLY_RECEIVED', 'RECEIVED', 'CLOSED'])
            ->groupBy('departments.id', 'departments.name')
            ->orderBy('total_spent', 'DESC')
            ->limit(10)
            ->get();

        return [
            'departments' => $spending->pluck('department_name'),
            'amounts' => $spending->pluck('total_spent'),
            'counts' => $spending->pluck('po_count'),
        ];
    }

    /**
     * Get top suppliers by order value
     */
    public static function getTopSuppliers(int $limit = 10, int $months = 12): array
    {
        $startDate = Carbon::now()->subMonths($months)->startOfMonth();

        $suppliers = DB::table('purchase_orders')
            ->select(
                'suppliers.name',
                DB::raw('COUNT(purchase_orders.id) as order_count'),
                DB::raw('SUM(purchase_orders.total_amount) as total_amount')
            )
            ->join('suppliers', 'purchase_orders.supplier_id', '=', 'suppliers.id')
            ->where('purchase_orders.created_at', '>=', $startDate)
            ->groupBy('suppliers.id', 'suppliers.name')
            ->orderBy('total_amount', 'DESC')
            ->limit($limit)
            ->get();

        return [
            'suppliers' => $suppliers->pluck('name'),
            'amounts' => $suppliers->pluck('total_amount'),
            'counts' => $suppliers->pluck('order_count'),
        ];
    }

    /**
     * Get procurement cycle time statistics
     */
    public static function getCycleTimeStats(): array
    {
        // Average days from PR creation to PO approval
        // Join through pivot table: PRs -> pivot -> POs
        $avgCycleTime = DB::table('purchase_requests')
            ->select(DB::raw('AVG(EXTRACT(EPOCH FROM (purchase_orders.approved_at - purchase_requests.created_at))/86400) as avg_days'))
            ->join('purchase_order_purchase_request', 'purchase_requests.id', '=', 'purchase_order_purchase_request.purchase_request_id')
            ->join('purchase_orders', 'purchase_order_purchase_request.purchase_order_id', '=', 'purchase_orders.id')
            ->whereNotNull('purchase_orders.approved_at')
            ->where('purchase_requests.created_at', '>=', Carbon::now()->subMonths(6))
            ->value('avg_days');

        return [
            'average_cycle_days' => round($avgCycleTime ?? 0, 1),
        ];
    }

    /**
     * Get comparison with previous period
     */
    public static function getPeriodComparison(string $period = 'month'): array
    {
        $currentStart = $period === 'month'
            ? Carbon::now()->startOfMonth()
            : Carbon::now()->subDays(7)->startOfDay();

        $previousStart = $period === 'month'
            ? Carbon::now()->subMonth()->startOfMonth()
            : Carbon::now()->subDays(14)->startOfDay();

        $previousEnd = $period === 'month'
            ? Carbon::now()->subMonth()->endOfMonth()
            : Carbon::now()->subDays(7)->endOfDay();

        // Count PRs (no prices in PR)
        $currentPRs = DB::table('purchase_requests')
            ->where('created_at', '>=', $currentStart)
            ->count();

        $previousPRs = DB::table('purchase_requests')
            ->whereBetween('created_at', [$previousStart, $previousEnd])
            ->count();

        // Calculate amounts from POs
        $currentAmount = DB::table('purchase_orders')
            ->leftJoin('purchase_order_lines', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->where('purchase_orders.created_at', '>=', $currentStart)
            ->sum(DB::raw('COALESCE(purchase_order_lines.quantity * purchase_order_lines.unit_price, 0)'));

        $previousAmount = DB::table('purchase_orders')
            ->leftJoin('purchase_order_lines', 'purchase_orders.id', '=', 'purchase_order_lines.purchase_order_id')
            ->whereBetween('purchase_orders.created_at', [$previousStart, $previousEnd])
            ->sum(DB::raw('COALESCE(purchase_order_lines.quantity * purchase_order_lines.unit_price, 0)'));

        $countChange = $previousPRs > 0
            ? (($currentPRs - $previousPRs) / $previousPRs) * 100
            : 0;

        $amountChange = $previousAmount > 0
            ? (($currentAmount - $previousAmount) / $previousAmount) * 100
            : 0;

        return [
            'current' => [
                'count' => $currentPRs,
                'amount' => $currentAmount ?? 0,
            ],
            'previous' => [
                'count' => $previousPRs,
                'amount' => $previousAmount ?? 0,
            ],
            'change' => [
                'count_percent' => round($countChange, 1),
                'amount_percent' => round($amountChange, 1),
            ],
        ];
    }
}
