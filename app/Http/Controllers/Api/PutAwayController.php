<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PutAway\CancelPutAwayRequest;
use App\Http\Requests\Api\PutAway\StorePutAwayRequest;
use App\Models\PutAway;
use App\Services\PutAway\PutAwayService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PutAwayController extends Controller
{
    public function __construct(
        private readonly PutAwayService $service,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PutAway::class);

        $query = PutAway::query()->with(['goodsReceipt', 'warehouse']);

        if ($search = $request->string('search')->toString()) {
            $query->where('put_away_number', 'ilike', '%' . $search . '%');
        }

        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }

        $perPage = (int) ($request->integer('per_page') ?: 20);

        return response()->json([
            'data' => $query->orderByDesc('id')->paginate($perPage)->withQueryString(),
        ]);
    }

    public function show(PutAway $putAway): JsonResponse
    {
        $this->authorize('view', $putAway);

        return response()->json([
            'data' => $putAway->load([
                'goodsReceipt',
                'warehouse',
                'lines.item',
                'lines.uom',
                'lines.sourceLocation',
                'lines.destinationLocation',
                'statusHistories.actor',
                'createdBy',
                'postedBy',
            ]),
        ]);
    }

    public function store(StorePutAwayRequest $request): JsonResponse
    {
        $this->authorize('create', PutAway::class);

        $putAway = $this->service->createDraft($request->user(), $request->validated());

        return response()->json(['data' => $putAway], 201);
    }

    public function post(Request $request, PutAway $putAway): JsonResponse
    {
        $this->authorize('post', $putAway);

        $putAway = $this->service->post($request->user(), $putAway->id);

        return response()->json(['data' => $putAway]);
    }

    public function cancel(CancelPutAwayRequest $request, PutAway $putAway): JsonResponse
    {
        $this->authorize('cancel', $putAway);

        $putAway = $this->service->cancel(
            $request->user(),
            $putAway->id,
            $request->validated()['reason'] ?? null,
        );

        return response()->json(['data' => $putAway]);
    }
}
