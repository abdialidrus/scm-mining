<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PickingOrder\CancelPickingOrderRequest;
use App\Http\Requests\Api\PickingOrder\StorePickingOrderRequest;
use App\Models\PickingOrder;
use App\Services\PickingOrder\PickingOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PickingOrderController extends Controller
{
    public function __construct(
        private readonly PickingOrderService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PickingOrder::class);

        $query = PickingOrder::query()->with(['department', 'warehouse']);

        if ($search = $request->string('search')->toString()) {
            $query->where('picking_order_number', 'ilike', '%' . $search . '%');
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $perPage = (int) ($request->integer('per_page') ?: 20);

        return response()->json([
            'data' => $query->orderByDesc('id')->paginate($perPage)->withQueryString(),
        ]);
    }

    public function show(PickingOrder $pickingOrder): JsonResponse
    {
        $this->authorize('view', $pickingOrder);

        return response()->json([
            'data' => $pickingOrder->load([
                'department',
                'warehouse',
                'lines.item',
                'lines.uom',
                'lines.sourceLocation',
                'statusHistories.actor',
                'createdBy',
                'postedBy',
            ]),
        ]);
    }

    public function store(StorePickingOrderRequest $request): JsonResponse
    {
        $this->authorize('create', PickingOrder::class);

        $pickingOrder = $this->service->createDraft($request->user(), $request->validated());

        return response()->json(['data' => $pickingOrder], 201);
    }

    public function post(Request $request, PickingOrder $pickingOrder): JsonResponse
    {
        $this->authorize('post', $pickingOrder);

        $pickingOrder = $this->service->post($request->user(), $pickingOrder->id);

        return response()->json(['data' => $pickingOrder]);
    }

    public function cancel(CancelPickingOrderRequest $request, PickingOrder $pickingOrder): JsonResponse
    {
        $this->authorize('cancel', $pickingOrder);

        $pickingOrder = $this->service->cancel(
            $request->user(),
            $pickingOrder->id,
            $request->validated('reason')
        );

        return response()->json(['data' => $pickingOrder]);
    }
}
