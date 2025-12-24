<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WarehouseLocation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseLocationController extends Controller
{
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
}
