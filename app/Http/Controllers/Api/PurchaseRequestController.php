<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PurchaseRequest\RejectPurchaseRequestRequest;
use App\Http\Requests\Api\PurchaseRequest\StorePurchaseRequestRequest;
use App\Http\Requests\Api\PurchaseRequest\UpdatePurchaseRequestRequest;
use App\Models\PurchaseRequest;
use App\Services\PurchaseRequest\PurchaseRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PurchaseRequestController extends Controller
{
    public function __construct(
        private readonly PurchaseRequestService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = PurchaseRequest::query()
            ->with(['department', 'requester'])
            ->orderByDesc('id');

        // Minimal scoping: user's department only.
        if ($user?->department_id) {
            $query->where('department_id', $user->department_id);
        }

        return response()->json([
            'data' => $query->paginate(20),
        ]);
    }

    public function show(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorize('view', $purchaseRequest);

        return response()->json([
            'data' => $purchaseRequest->load(['lines.item.baseUom', 'lines.uom', 'department.head', 'requester', 'approvedBy']),
        ]);
    }

    public function store(StorePurchaseRequestRequest $request): JsonResponse
    {
        $pr = $this->service->createDraft($request->user(), $request->validated());

        return response()->json(['data' => $pr], 201);
    }

    public function update(UpdatePurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorize('update', $purchaseRequest);

        $pr = $this->service->updateDraft($request->user(), $purchaseRequest->id, $request->validated());

        return response()->json(['data' => $pr]);
    }

    public function submit(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorize('submit', $purchaseRequest);

        $pr = $this->service->submit($request->user(), $purchaseRequest->id);

        return response()->json(['data' => $pr]);
    }

    public function approve(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorize('approve', $purchaseRequest);

        $pr = $this->service->approve($request->user(), $purchaseRequest->id);

        return response()->json(['data' => $pr]);
    }

    public function reject(RejectPurchaseRequestRequest $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        $this->authorize('approve', $purchaseRequest);

        $pr = $this->service->reject($request->user(), $purchaseRequest->id, $request->validated()['reason'] ?? null);

        return response()->json(['data' => $pr]);
    }

    public function convertToPo(Request $request, PurchaseRequest $purchaseRequest): JsonResponse
    {
        // Authorization for conversion will be refined once PO module exists.
        // For now, require authenticated user and valid state transition.
        $pr = $this->service->convertToPo($request->user(), $purchaseRequest->id);

        return response()->json(['data' => $pr]);
    }
}
