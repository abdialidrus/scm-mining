<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Api\Warehouse\UpdateWarehouseRequest;
use App\Models\Warehouse;
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

        return response()->json([
            'data' => $query->get(['id', 'code', 'name', 'address', 'is_active']),
        ]);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        $this->authorize('view', $warehouse);

        return response()->json([
            'data' => $warehouse,
        ]);
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $this->authorize('create', Warehouse::class);

        $wh = Warehouse::query()->create($request->validated());

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
