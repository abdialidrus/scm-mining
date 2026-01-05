<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\WarehouseLocation\StoreWarehouseLocationRequest;
use App\Http\Requests\Api\WarehouseLocation\UpdateWarehouseLocationRequest;
use App\Models\WarehouseLocation;
use App\Services\Warehouse\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseLocationController extends Controller
{
    public function __construct(
        private LocationService $locationService
    ) {}

    public function index(Request $request): JsonResponse
    {
        // MVP: reuse warehouse/manage permission (warehouse role has it)
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['super_admin', 'warehouse'])) {
            abort(403);
        }

        $query = WarehouseLocation::query();

        if ($warehouseId = $request->integer('warehouse_id')) {
            $query->where('warehouse_id', (int) $warehouseId);
        }

        if ($type = $request->string('type')->toString()) {
            $query->where('type', $type);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOL));
        }

        // Optional: request a single default location (within current filters)
        if ($request->boolean('only_default')) {
            $row = (clone $query)
                ->where('is_default', true)
                ->orderBy('code')
                ->first(['id', 'warehouse_id', 'type', 'code', 'name', 'is_active']);

            return response()->json(['data' => $row ? [$row] : []]);
        }

        $rows = $query
            ->orderBy('type')
            ->orderBy('code')
            ->get(['id', 'warehouse_id', 'type', 'code', 'name', 'is_active']);

        return response()->json(['data' => $rows]);
    }

    /**
     * Get a single warehouse location with details
     */
    public function show(WarehouseLocation $warehouse_location): JsonResponse
    {
        $this->authorize('view', $warehouse_location);

        $warehouse_location->load(['warehouse', 'parent']);

        return response()->json([
            'data' => $warehouse_location,
        ]);
    }

    /**
     * Create a new warehouse location
     */
    public function store(StoreWarehouseLocationRequest $request): JsonResponse
    {
        $this->authorize('create', WarehouseLocation::class);

        $location = $this->locationService->createLocation($request->validated());

        return response()->json([
            'data' => $location->load(['warehouse', 'parent']),
            'message' => 'Location created successfully.',
        ], 201);
    }

    /**
     * Update an existing warehouse location
     */
    public function update(UpdateWarehouseLocationRequest $request, WarehouseLocation $warehouse_location): JsonResponse
    {
        $this->authorize('update', $warehouse_location);

        $location = $this->locationService->updateLocation($warehouse_location, $request->validated());

        return response()->json([
            'data' => $location->load(['warehouse', 'parent']),
            'message' => 'Location updated successfully.',
        ]);
    }

    /**
     * Deactivate a warehouse location (soft delete)
     */
    public function destroy(WarehouseLocation $warehouse_location): JsonResponse
    {
        $this->authorize('delete', $warehouse_location);

        $this->locationService->deactivateLocation($warehouse_location);

        return response()->json([
            'message' => 'Location deactivated successfully.',
        ]);
    }

    /**
     * Get stock summary for a location
     */
    public function stockSummary(WarehouseLocation $warehouse_location): JsonResponse
    {
        $this->authorize('view', $warehouse_location);

        $summary = $this->locationService->getStockSummary($warehouse_location);

        return response()->json([
            'data' => $summary,
        ]);
    }

    /**
     * Get recent movements for a location
     */
    public function recentMovements(WarehouseLocation $warehouse_location, Request $request): JsonResponse
    {
        $this->authorize('view', $warehouse_location);

        $limit = min((int) $request->query('limit', 20), 100);

        $movements = $this->locationService->getRecentMovements($warehouse_location, $limit);

        return response()->json([
            'data' => $movements,
        ]);
    }

    /**
     * Get stock by item for a location
     */
    public function stockByItem(WarehouseLocation $warehouse_location): JsonResponse
    {
        $this->authorize('view', $warehouse_location);

        $stockItems = $this->locationService->getStockByItem($warehouse_location);

        return response()->json([
            'data' => $stockItems,
        ]);
    }
}
