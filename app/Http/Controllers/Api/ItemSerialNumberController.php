<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ItemSerialNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemSerialNumberController extends Controller
{
    /**
     * Get available serial numbers for picking.
     *
     * Query params:
     * - item_id (required)
     * - location_id (optional)
     * - status (optional, default: AVAILABLE)
     */
    public function available(Request $request): JsonResponse
    {
        $itemId = $request->integer('item_id');
        $locationId = $request->integer('location_id') ?: null;
        $status = $request->string('status')->toString() ?: ItemSerialNumber::STATUS_AVAILABLE;

        if (!$itemId) {
            return response()->json([
                'error' => 'item_id is required',
            ], 400);
        }

        $query = ItemSerialNumber::query()
            ->where('item_id', $itemId)
            ->where('status', $status);

        if ($locationId) {
            $query->where('current_location_id', $locationId);
        }

        $serials = $query
            ->orderBy('serial_number')
            ->get(['id', 'item_id', 'serial_number', 'status', 'current_location_id', 'received_at']);

        return response()->json([
            'data' => $serials,
        ]);
    }

    /**
     * Get serial number details.
     */
    public function show(string $serialNumber): JsonResponse
    {
        $serial = ItemSerialNumber::query()
            ->with(['item', 'currentLocation'])
            ->where('serial_number', $serialNumber)
            ->firstOrFail();

        return response()->json([
            'data' => $serial,
        ]);
    }
}
