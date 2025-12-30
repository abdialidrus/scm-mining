<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        // Create Main Warehouse
        $warehouse = Warehouse::query()->updateOrCreate(
            ['code' => 'WH-MAIN'],
            [
                'name' => 'Main Warehouse',
                'address' => 'Jl. Pertambangan No. 123, Site Area',
                'is_active' => true,
            ],
        );

        // Create default RECEIVING location (required by system)
        WarehouseLocation::query()->updateOrCreate(
            [
                'warehouse_id' => $warehouse->id,
                'code' => 'RCV-01',
            ],
            [
                'type' => WarehouseLocation::TYPE_RECEIVING,
                'name' => 'Main Receiving Area',
                'is_default' => true,
                'is_active' => true,
            ],
        );

        // Create storage locations
        $storageLocations = [
            [
                'code' => 'ZONE-A',
                'name' => 'Storage Zone A - Spare Parts',
                'is_default' => false,
            ],
            [
                'code' => 'ZONE-B',
                'name' => 'Storage Zone B - Consumables',
                'is_default' => false,
            ],
            [
                'code' => 'ZONE-C',
                'name' => 'Storage Zone C - PPE & Safety',
                'is_default' => false,
            ],
        ];

        foreach ($storageLocations as $location) {
            WarehouseLocation::query()->updateOrCreate(
                [
                    'warehouse_id' => $warehouse->id,
                    'code' => $location['code'],
                ],
                [
                    'type' => WarehouseLocation::TYPE_STORAGE,
                    'name' => $location['name'],
                    'is_default' => $location['is_default'],
                    'is_active' => true,
                    'parent_id' => null,
                ],
            );
        }

        $this->command->info('✅ Warehouse and locations seeded successfully');
    }
}
