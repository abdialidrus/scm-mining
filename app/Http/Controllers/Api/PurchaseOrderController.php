<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PurchaseOrder\CancelPurchaseOrderRequest;
use App\Http\Requests\Api\PurchaseOrder\PurchaseOrderReportRequest;
use App\Http\Requests\Api\PurchaseOrder\ReopenPurchaseOrderRequest;
use App\Http\Requests\Api\PurchaseOrder\StorePurchaseOrderRequest;
use App\Http\Requests\Api\PurchaseOrder\UpdatePurchaseOrderDraftRequest;
use App\Models\PurchaseOrder;
use App\Services\Approval\ApprovalWorkflowService;
use App\Services\PurchaseOrder\PurchaseOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(
        private readonly PurchaseOrderService $service,
        private readonly ApprovalWorkflowService $approvalWorkflowService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $query = PurchaseOrder::query()
            ->with(['supplier'])
            ->orderByDesc('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('po_number', 'ilike', '%' . $search . '%')
                    ->orWhereHas('supplier', function ($qs) use ($search) {
                        $qs->where('name', 'ilike', '%' . $search . '%')
                            ->orWhere('code', 'ilike', '%' . $search . '%');
                    });
            });
        }

        return response()->json([
            'data' => $query->paginate(20)->withQueryString(),
        ]);
    }

    public function show(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('view', $purchaseOrder);

        return response()->json([
            'data' => $purchaseOrder->load([
                'supplier',
                'lines.item',
                'lines.uom',
                'purchaseRequests.department',
                'purchaseRequests.requester',
                'statusHistories.actor',
                'submittedBy',
                'approvedBy',
                'approvals.step',
                'approvals.assignedToUser',
                'approvals.approvedBy',
                'approvals.rejectedBy',
            ]),
        ]);
    }

    public function approvals(PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('view', $purchaseOrder);

        $approvals = $this->approvalWorkflowService->getApprovals($purchaseOrder);

        return response()->json([
            'data' => $approvals,
        ]);
    }

    public function store(StorePurchaseOrderRequest $request): JsonResponse
    {
        $this->authorize('create', PurchaseOrder::class);

        $po = $this->service->createDraftFromPurchaseRequests($request->user(), $request->validated());

        return response()->json(['data' => $po], 201);
    }

    public function submit(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('submit', $purchaseOrder);

        $po = $this->service->submit($request->user(), $purchaseOrder->id);

        return response()->json(['data' => $po]);
    }

    public function approve(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('approve', $purchaseOrder);

        $po = $this->service->approve($request->user(), $purchaseOrder->id);

        return response()->json(['data' => $po]);
    }

    public function reject(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('reject', $purchaseOrder);

        $reason = $request->input('reason', 'Rejected');

        $po = $this->service->reject($request->user(), $purchaseOrder->id, $reason);

        return response()->json(['data' => $po]);
    }

    public function send(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('send', $purchaseOrder);

        $po = $this->service->send($request->user(), $purchaseOrder->id);

        return response()->json(['data' => $po]);
    }

    public function close(Request $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('close', $purchaseOrder);

        $po = $this->service->close($request->user(), $purchaseOrder->id);

        return response()->json(['data' => $po]);
    }

    public function cancel(CancelPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('cancel', $purchaseOrder);

        $po = $this->service->cancel($request->user(), $purchaseOrder->id, $request->validated()['reason'] ?? null);

        return response()->json(['data' => $po]);
    }

    public function updateDraft(UpdatePurchaseOrderDraftRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('updateDraft', $purchaseOrder);

        $po = $this->service->updateDraft($request->user(), $purchaseOrder->id, $request->validated());

        return response()->json(['data' => $po]);
    }

    public function reopen(ReopenPurchaseOrderRequest $request, PurchaseOrder $purchaseOrder): JsonResponse
    {
        $this->authorize('reopen', $purchaseOrder);

        $po = $this->service->reopen($request->user(), $purchaseOrder->id, $request->validated()['reason'] ?? null);

        return response()->json(['data' => $po]);
    }

    public function report(PurchaseOrderReportRequest $request): JsonResponse
    {
        $this->authorize('viewAny', PurchaseOrder::class);

        $data = $request->validated();

        $dateField = $data['date_field'] ?? 'created_at';
        $dateFrom = $data['date_from'] ?? null;
        $dateTo = $data['date_to'] ?? null;
        $status = $data['status'] ?? null;
        $supplierId = $data['supplier_id'] ?? null;
        $currencyCode = $data['currency_code'] ?? null;
        $groupByStatus = (bool) ($data['group_by_status'] ?? false);

        $query = PurchaseOrder::query()->whereNotNull('total_amount');

        if ($status) {
            $query->where('status', $status);
        }

        if ($supplierId) {
            $query->where('supplier_id', (int) $supplierId);
        }

        if ($currencyCode) {
            $query->where('currency_code', $currencyCode);
        }

        if ($dateFrom) {
            $query->whereDate($dateField, '>=', $dateFrom);
        }

        if ($dateTo) {
            $query->whereDate($dateField, '<=', $dateTo);
        }

        $count = (clone $query)->count();
        $sumTotal = (clone $query)->sum('total_amount');
        $sumSubtotal = (clone $query)->sum('subtotal_amount');
        $sumTax = (clone $query)->sum('tax_amount');

        $breakdownByStatus = null;
        if ($groupByStatus) {
            $breakdownByStatus = (clone $query)
                ->selectRaw('status, COUNT(*) as po_count, COALESCE(SUM(subtotal_amount),0) as subtotal_sum, COALESCE(SUM(tax_amount),0) as tax_sum, COALESCE(SUM(total_amount),0) as total_sum')
                ->groupBy('status')
                ->orderBy('status')
                ->get();
        }

        return response()->json([
            'data' => [
                'filters' => [
                    'date_field' => $dateField,
                    'date_from' => $dateFrom,
                    'date_to' => $dateTo,
                    'status' => $status,
                    'supplier_id' => $supplierId,
                    'currency_code' => $currencyCode,
                ],
                'count' => $count,
                'subtotal_sum' => (float) $sumSubtotal,
                'tax_sum' => (float) $sumTax,
                'total_sum' => (float) $sumTotal,
                'breakdown_by_status' => $breakdownByStatus,
            ],
        ]);
    }
}
