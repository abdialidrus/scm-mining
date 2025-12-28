<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\StockMovement;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use App\Services\Inventory\StockQueryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockReportController extends Controller
{
    public function __construct(
        private readonly StockQueryService $stockQueryService,
    ) {}

    /**
     * Get stock on-hand by location.
     *
     * Query params:
     *  - warehouse_id (optional)
     *  - item_id (optional)
     *  - search (item sku/name search)
     *  - location_type (RECEIVING, STORAGE, optional)
     *  - page, per_page
     */
    public function stockByLocation(Request $request): JsonResponse
    {
        // Basic permission check
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['super_admin', 'warehouse', 'procurement'])) {
            abort(403, 'Unauthorized');
        }

        $warehouseId = $request->integer('warehouse_id') ?: null;
        $itemId = $request->integer('item_id') ?: null;
        $search = trim((string) $request->query('search', ''));
        $locationType = $request->string('location_type')->toString();

        // Build query to get all items with their locations
        $query = DB::table('items')
            ->select([
                'items.id as item_id',
                'items.sku',
                'items.name as item_name',
                'items.base_uom_id',
                'uoms.code as uom_code',
                'uoms.name as uom_name',
                'warehouse_locations.id as location_id',
                'warehouse_locations.warehouse_id',
                'warehouse_locations.type as location_type',
                'warehouse_locations.code as location_code',
                'warehouse_locations.name as location_name',
                'warehouses.code as warehouse_code',
                'warehouses.name as warehouse_name',
            ])
            ->leftJoin('uoms', 'uoms.id', '=', 'items.base_uom_id')
            ->crossJoin('warehouse_locations')
            ->join('warehouses', 'warehouses.id', '=', 'warehouse_locations.warehouse_id')
            ->where('warehouse_locations.is_active', true)
            ->orderBy('warehouses.code')
            ->orderBy('warehouse_locations.type')
            ->orderBy('warehouse_locations.code')
            ->orderBy('items.sku');

        if ($warehouseId) {
            $query->where('warehouse_locations.warehouse_id', $warehouseId);
        }

        if ($itemId) {
            $query->where('items.id', $itemId);
        }

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('items.sku', 'ilike', '%' . $search . '%')
                    ->orWhere('items.name', 'ilike', '%' . $search . '%');
            });
        }

        if ($locationType !== '') {
            $query->where('warehouse_locations.type', $locationType);
        }

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        // Get all matching rows (without pagination first) to calculate stock
        $allRows = $query->get();

        // Calculate on-hand qty and filter items with stock > 0
        $dataWithStock = $allRows->map(function ($row) {
            $onHand = $this->stockQueryService->getOnHandForLocation(
                (int) $row->location_id,
                (int) $row->item_id,
                $row->base_uom_id ? (int) $row->base_uom_id : null,
            );

            return [
                'item_id' => (int) $row->item_id,
                'sku' => $row->sku,
                'item_name' => $row->item_name,
                'location_id' => (int) $row->location_id,
                'location_code' => $row->location_code,
                'location_name' => $row->location_name,
                'location_type' => $row->location_type,
                'warehouse_id' => (int) $row->warehouse_id,
                'warehouse_code' => $row->warehouse_code,
                'warehouse_name' => $row->warehouse_name,
                'qty_on_hand' => $onHand,
                'uom_code' => $row->uom_code,
                'uom_name' => $row->uom_name,
            ];
        })->filter(function ($row) {
            // Only show rows with stock
            return $row['qty_on_hand'] > 0;
        })->values();

        // Manual pagination
        $total = $dataWithStock->count();
        $currentPage = (int) $request->query('page', 1);
        $currentPage = max(1, $currentPage);
        $lastPage = (int) ceil($total / $perPage);
        $currentPage = min($currentPage, max(1, $lastPage));

        $offset = ($currentPage - 1) * $perPage;
        $items = $dataWithStock->slice($offset, $perPage)->values();

        return response()->json([
            'data' => [
                'items' => $items,
                'meta' => [
                    'current_page' => $currentPage,
                    'from' => $total > 0 ? $offset + 1 : null,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'to' => $total > 0 ? min($offset + $perPage, $total) : null,
                    'total' => $total,
                ],
                'links' => [
                    'first' => $request->url() . '?' . http_build_query(array_merge($request->query(), ['page' => 1])),
                    'last' => $request->url() . '?' . http_build_query(array_merge($request->query(), ['page' => $lastPage])),
                    'prev' => $currentPage > 1 ? $request->url() . '?' . http_build_query(array_merge($request->query(), ['page' => $currentPage - 1])) : null,
                    'next' => $currentPage < $lastPage ? $request->url() . '?' . http_build_query(array_merge($request->query(), ['page' => $currentPage + 1])) : null,
                ],
            ],
        ]);
    }

    /**
     * Get stock summary by item (total across all locations).
     *
     * Query params:
     *  - warehouse_id (optional)
     *  - search (item sku/name)
     *  - page, per_page
     */
    public function stockSummaryByItem(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['super_admin', 'warehouse', 'procurement'])) {
            abort(403, 'Unauthorized');
        }

        $warehouseId = $request->integer('warehouse_id') ?: null;
        $search = trim((string) $request->query('search', ''));

        $query = Item::query()
            ->with('baseUom:id,code,name')
            ->select(['id', 'sku', 'name', 'base_uom_id'])
            ->orderBy('sku');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'ilike', '%' . $search . '%')
                    ->orWhere('name', 'ilike', '%' . $search . '%');
            });
        }

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        // Get all items matching the search criteria
        $allItems = $query->get();

        // Calculate stock for each item
        $dataWithStock = $allItems->map(function ($item) use ($warehouseId) {
            $byLocation = $this->stockQueryService->getOnHandByLocationForItem(
                $item->id,
                $item->base_uom_id,
            );

            // If warehouse filter, only sum locations in that warehouse
            $total = 0.0;
            if ($warehouseId) {
                $locationIds = WarehouseLocation::query()
                    ->where('warehouse_id', $warehouseId)
                    ->pluck('id')
                    ->all();

                foreach ($locationIds as $locId) {
                    $total += $byLocation[$locId] ?? 0;
                }
            } else {
                $total = array_sum($byLocation);
            }

            return [
                'item_id' => $item->id,
                'sku' => $item->sku,
                'name' => $item->name,
                'qty_on_hand' => $total,
                'uom_code' => $item->baseUom?->code,
                'uom_name' => $item->baseUom?->name,
                'locations_count' => count(array_filter($byLocation, fn($q) => $q > 0)),
            ];
        })->filter(function ($row) {
            // Only show items with stock
            return $row['qty_on_hand'] > 0;
        })->values();

        // Manual pagination
        $total = $dataWithStock->count();
        $currentPage = (int) $request->query('page', 1);
        $currentPage = max(1, $currentPage);
        $lastPage = (int) ceil($total / $perPage);
        $currentPage = min($currentPage, max(1, $lastPage));

        $offset = ($currentPage - 1) * $perPage;
        $items = $dataWithStock->slice($offset, $perPage)->values();

        return response()->json([
            'data' => [
                'items' => $items,
                'meta' => [
                    'current_page' => $currentPage,
                    'from' => $total > 0 ? $offset + 1 : null,
                    'last_page' => $lastPage,
                    'per_page' => $perPage,
                    'to' => $total > 0 ? min($offset + $perPage, $total) : null,
                    'total' => $total,
                ],
                'links' => [
                    'first' => $request->url() . '?' . http_build_query(array_merge($request->query(), ['page' => 1])),
                    'last' => $request->url() . '?' . http_build_query(array_merge($request->query(), ['page' => $lastPage])),
                    'prev' => $currentPage > 1 ? $request->url() . '?' . http_build_query(array_merge($request->query(), ['page' => $currentPage - 1])) : null,
                    'next' => $currentPage < $lastPage ? $request->url() . '?' . http_build_query(array_merge($request->query(), ['page' => $currentPage + 1])) : null,
                ],
            ],
        ]);
    }

    /**
     * Get stock movement history.
     *
     * Query params:
     *  - item_id (optional)
     *  - warehouse_id (optional, filter by source or destination warehouse)
     *  - location_id (optional, filter by source or destination location)
     *  - reference_type (optional: GOODS_RECEIPT, PUT_AWAY, ADJUSTMENT)
     *  - date_from, date_to (movement_at filter)
     *  - page, per_page
     */
    public function movements(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['super_admin', 'warehouse', 'procurement'])) {
            abort(403, 'Unauthorized');
        }

        $query = StockMovement::query()
            ->with([
                'item:id,sku,name',
                'uom:id,code,name',
                'sourceLocation:id,warehouse_id,type,code,name',
                'destinationLocation:id,warehouse_id,type,code,name',
                'creator:id,name,email',
            ])
            ->orderByDesc('movement_at')
            ->orderByDesc('id');

        if ($itemId = $request->integer('item_id')) {
            $query->where('item_id', $itemId);
        }

        if ($warehouseId = $request->integer('warehouse_id')) {
            $query->where(function ($q) use ($warehouseId) {
                $q->whereHas('sourceLocation', function ($qs) use ($warehouseId) {
                    $qs->where('warehouse_id', $warehouseId);
                })->orWhereHas('destinationLocation', function ($qd) use ($warehouseId) {
                    $qd->where('warehouse_id', $warehouseId);
                });
            });
        }

        if ($locationId = $request->integer('location_id')) {
            $query->where(function ($q) use ($locationId) {
                $q->where('source_location_id', $locationId)
                    ->orWhere('destination_location_id', $locationId);
            });
        }

        if ($refType = $request->string('reference_type')->toString()) {
            $query->where('reference_type', $refType);
        }

        if ($dateFrom = $request->query('date_from')) {
            $query->where('movement_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->query('date_to')) {
            $query->where('movement_at', '<=', $dateTo);
        }

        $perPage = max(1, min((int) $request->query('per_page', 20), 100));

        return response()->json([
            'data' => $query->paginate($perPage)->withQueryString(),
        ]);
    }

    /**
     * Get item stock detail (by location breakdown).
     *
     * GET /api/stock-reports/items/{item}/locations?warehouse_id=1
     */
    public function itemLocationBreakdown(Request $request, Item $item): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['super_admin', 'warehouse', 'procurement'])) {
            abort(403, 'Unauthorized');
        }

        $warehouseId = $request->integer('warehouse_id') ?: null;

        $byLocation = $this->stockQueryService->getOnHandByLocationForItem(
            $item->id,
            $item->base_uom_id,
        );

        // Load location details
        $locationIds = array_keys(array_filter($byLocation, fn($q) => $q > 0));

        $locationsQuery = WarehouseLocation::query()
            ->with('warehouse:id,code,name')
            ->whereIn('id', $locationIds);

        if ($warehouseId) {
            $locationsQuery->where('warehouse_id', $warehouseId);
        }

        $locations = $locationsQuery->get();

        $data = $locations->map(function ($loc) use ($byLocation) {
            return [
                'location_id' => $loc->id,
                'location_code' => $loc->code,
                'location_name' => $loc->name,
                'location_type' => $loc->type,
                'warehouse_id' => $loc->warehouse_id,
                'warehouse_code' => $loc->warehouse?->code,
                'warehouse_name' => $loc->warehouse?->name,
                'qty_on_hand' => $byLocation[$loc->id] ?? 0,
            ];
        })->filter(function ($row) {
            return $row['qty_on_hand'] > 0;
        })->values();

        return response()->json([
            'data' => [
                'item' => [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'name' => $item->name,
                    'uom_code' => $item->baseUom?->code,
                    'uom_name' => $item->baseUom?->name,
                ],
                'locations' => $data,
                'total_qty' => array_sum(array_column($data->toArray(), 'qty_on_hand')),
            ],
        ]);
    }
}
