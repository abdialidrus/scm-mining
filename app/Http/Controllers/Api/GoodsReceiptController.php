<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GoodsReceipt\CancelGoodsReceiptRequest;
use App\Http\Requests\Api\GoodsReceipt\StoreGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Models\PutAway;
use App\Services\GoodsReceipt\GoodsReceiptPutAwaySummaryService;
use App\Services\GoodsReceipt\GoodsReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly GoodsReceiptService $service,
        private readonly GoodsReceiptPutAwaySummaryService $putAwaySummaryService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        $search = trim((string) $request->query('search', ''));
        $status = trim((string) $request->query('status', ''));

        $query = GoodsReceipt::query()
            ->with(['purchaseOrder:id,po_number', 'warehouse:id,code,name'])
            ->orderByDesc('id');

        if ($status !== '') {
            $query->where('status', $status);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('gr_number', 'ilike', '%' . $search . '%')
                    ->orWhereHas('purchaseOrder', function ($qpo) use ($search) {
                        $qpo->where('po_number', 'ilike', '%' . $search . '%');
                    });
            });
        }

        return response()->json([
            'data' => $query->paginate(20)->withQueryString(),
        ]);
    }

    /**
     * Eligible GRs for Put Away creation.
     *
     * Query params:
     *  - search (gr_number / po_number)
     *  - warehouse_id
     *  - page
     *  - per_page
     *  - only_with_remaining (default true)
     */
    public function eligibleForPutAway(Request $request): JsonResponse
    {
        $this->authorize('viewAny', GoodsReceipt::class);

        $search = trim((string) $request->query('search', ''));
        $warehouseId = (int) $request->query('warehouse_id', 0);

        $perPage = (int) $request->query('per_page', 10);
        if ($perPage <= 0) {
            $perPage = 10;
        }
        $perPage = min($perPage, 100);

        $onlyWithRemaining = $request->has('only_with_remaining')
            ? filter_var($request->query('only_with_remaining'), FILTER_VALIDATE_BOOL)
            : true;

        $receivedAgg = DB::table('goods_receipt_lines')
            ->selectRaw('goods_receipt_id, SUM(received_quantity) as received_total')
            ->groupBy('goods_receipt_id');

        $putAwayAgg = DB::table('put_away_lines')
            ->join('put_aways', 'put_aways.id', '=', 'put_away_lines.put_away_id')
            ->where('put_aways.status', PutAway::STATUS_POSTED)
            ->selectRaw('put_aways.goods_receipt_id as goods_receipt_id, SUM(put_away_lines.qty) as put_away_total')
            ->groupBy('put_aways.goods_receipt_id');

        $query = GoodsReceipt::query()
            ->with(['purchaseOrder:id,po_number', 'warehouse:id,code,name'])
            ->whereIn('status', [GoodsReceipt::STATUS_POSTED, GoodsReceipt::STATUS_PUT_AWAY_PARTIAL])
            ->leftJoinSub($receivedAgg, 'gr_recv', function ($join) {
                $join->on('gr_recv.goods_receipt_id', '=', 'goods_receipts.id');
            })
            ->leftJoinSub($putAwayAgg, 'gr_pa', function ($join) {
                $join->on('gr_pa.goods_receipt_id', '=', 'goods_receipts.id');
            })
            ->select([
                'goods_receipts.*',
                DB::raw('COALESCE(gr_recv.received_total, 0) as received_total'),
                DB::raw('COALESCE(gr_pa.put_away_total, 0) as put_away_total'),
                DB::raw('(COALESCE(gr_recv.received_total, 0) - COALESCE(gr_pa.put_away_total, 0)) as remaining_total'),
            ])
            ->orderByDesc('goods_receipts.id');

        if ($warehouseId > 0) {
            $query->where('goods_receipts.warehouse_id', $warehouseId);
        }

        if ($onlyWithRemaining) {
            $query->whereRaw('(COALESCE(gr_recv.received_total, 0) - COALESCE(gr_pa.put_away_total, 0)) > 0');
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('goods_receipts.gr_number', 'ilike', '%' . $search . '%')
                    ->orWhereHas('purchaseOrder', function ($qpo) use ($search) {
                        $qpo->where('po_number', 'ilike', '%' . $search . '%');
                    });
            });
        }

        return response()->json([
            'data' => $query->paginate($perPage)->withQueryString(),
        ]);
    }

    /**
     * Put away summary for a GR (per line): received, already put away (POSTED), remaining.
     */
    public function putAwaySummary(GoodsReceipt $goodsReceipt): JsonResponse
    {
        $this->authorize('view', $goodsReceipt);

        return response()->json([
            'data' => $this->putAwaySummaryService->getLineSummary($goodsReceipt->id),
        ]);
    }

    public function show(GoodsReceipt $goodsReceipt): JsonResponse
    {
        $this->authorize('view', $goodsReceipt);

        return response()->json([
            'data' => $goodsReceipt->load([
                'purchaseOrder.supplier',
                'warehouse',
                'lines.item',
                'lines.uom',
                'statusHistories.actor',
                'postedBy',
            ]),
        ]);
    }

    public function store(StoreGoodsReceiptRequest $request): JsonResponse
    {
        $this->authorize('create', GoodsReceipt::class);

        $gr = $this->service->createDraft($request->user(), $request->validated());

        return response()->json(['data' => $gr], 201);
    }

    public function post(Request $request, GoodsReceipt $goodsReceipt): JsonResponse
    {
        $this->authorize('post', $goodsReceipt);

        $gr = $this->service->post($request->user(), $goodsReceipt->id);

        return response()->json(['data' => $gr]);
    }

    public function cancel(CancelGoodsReceiptRequest $request, GoodsReceipt $goodsReceipt): JsonResponse
    {
        $this->authorize('cancel', $goodsReceipt);

        $gr = $this->service->cancel($request->user(), $goodsReceipt->id, $request->validated()['reason'] ?? null);

        return response()->json(['data' => $gr]);
    }
}
