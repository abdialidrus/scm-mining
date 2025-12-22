<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\GoodsReceipt\CancelGoodsReceiptRequest;
use App\Http\Requests\Api\GoodsReceipt\StoreGoodsReceiptRequest;
use App\Models\GoodsReceipt;
use App\Services\GoodsReceipt\GoodsReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GoodsReceiptController extends Controller
{
    public function __construct(
        private readonly GoodsReceiptService $service,
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
