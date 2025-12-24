<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Api\Warehouse\UpdateWarehouseRequest;
use App\Models\Warehouse;
use App\Services\WarehouseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Warehouse::class);

        $search = trim((string) $request->query('search', ''));

        $query = Warehouse::query()->orderBy('code');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ilike', '%' . $search . '%')
                    ->orWhere('name', 'ilike', '%' . $search . '%');
            });
        }

        $perPage = (int) $request->query('per_page', 10);
        $perPage = max(1, min($perPage, 100));

        $paginator = $query
            ->select(['id', 'code', 'name', 'address', 'is_active'])
            ->paginate($perPage)
            ->appends($request->query());

        return response()->json([
            'data' => $paginator,
        ]);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('view', $warehouse);

        return response()->json([
            'data' => $warehouse,
        ]);
    }

    public function store(StoreWarehouseRequest $request, WarehouseService $warehouseService): JsonResponse
    {
        $this->authorize('create', Warehouse::class);

        $wh = $warehouseService->createWarehouse($request->validated());

        return response()->json([
            'data' => $wh,
        ], 201);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $this->authorize('update', $warehouse);

        $warehouse->fill($request->validated());
        $warehouse->save();

        return response()->json([
            'data' => $warehouse,
        ]);
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('delete', $warehouse);

        $warehouse->delete();

        return response()->json(['message' => 'Deleted']);
    }
}
