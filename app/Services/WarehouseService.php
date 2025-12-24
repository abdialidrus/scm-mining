<?php

namespace App\Services;

use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class WarehouseService
{
    /**
     * @param array<string,mixed> $payload
     */
    public function createWarehouse(array $payload): Warehouse
    {
        return DB::transaction(function () use ($payload) {
            $warehouse = Warehouse::query()->create([
                'code' => Arr::get($payload, 'code'),
                'name' => Arr::get($payload, 'name'),
                'address' => Arr::get($payload, 'address'),
                'is_active' => (bool) Arr::get($payload, 'is_active', true),
            ]);

            // Always create default RECEIVING location.
            WarehouseLocation::query()->create([
                'warehouse_id' => $warehouse->id,
                'parent_id' => null,
                'type' => WarehouseLocation::TYPE_RECEIVING,
                'code' => 'RCV',
                'name' => 'Receiving',
                'is_default' => true,
                'is_active' => true,
            ]);

            // Optional STORAGE auto-create.
            $autoCreateStorage = (bool) Arr::get($payload, 'auto_create_storage', false);
            if ($autoCreateStorage) {
                WarehouseLocation::query()->create([
                    'warehouse_id' => $warehouse->id,
                    'parent_id' => null,
                    'type' => WarehouseLocation::TYPE_STORAGE,
                    'code' => 'STO',
                    'name' => 'Storage',
                    'is_default' => true,
                    'is_active' => true,
                ]);
            }

            return $warehouse;
        });
    }
}
