<?php

namespace App\Services\Inventory;

use App\Models\StockMovement;
use App\Models\WarehouseLocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockMovementService
{
    /**
     * Create a stock movement ledger entry.
     *
     * Business rules enforced here (not in controllers):
     * - qty must be > 0
     * - source/destination cannot both be null
     * - if both provided, they must belong to the same warehouse
     *
     * @param array<string,mixed> $payload
     */
    public function createMovement(array $payload): StockMovement
    {
        return DB::transaction(function () use ($payload) {
            $qty = (float) ($payload['qty'] ?? 0);
            if ($qty <= 0) {
                throw ValidationException::withMessages(['qty' => 'Quantity must be greater than 0.']);
            }

            $sourceId = $payload['source_location_id'] ?? null;
            $destId = $payload['destination_location_id'] ?? null;

            if ($sourceId === null && $destId === null) {
                throw ValidationException::withMessages([
                    'destination_location_id' => 'Source or destination location must be provided.',
                ]);
            }

            if ($sourceId !== null && $destId !== null) {
                $source = WarehouseLocation::query()->findOrFail($sourceId);
                $dest = WarehouseLocation::query()->findOrFail($destId);

                if ((int) $source->warehouse_id !== (int) $dest->warehouse_id) {
                    throw ValidationException::withMessages([
                        'destination_location_id' => 'Source and destination must be in the same warehouse.',
                    ]);
                }
            }

            return StockMovement::query()->create([
                'item_id' => $payload['item_id'],
                'uom_id' => $payload['uom_id'] ?? null,
                'source_location_id' => $sourceId,
                'destination_location_id' => $destId,
                'qty' => $payload['qty'],
                'reference_type' => $payload['reference_type'],
                'reference_id' => $payload['reference_id'],
                'created_by' => $payload['created_by'] ?? null,
                'movement_at' => $payload['movement_at'] ?? now(),
                'meta' => $payload['meta'] ?? null,
            ]);
        });
    }
}
