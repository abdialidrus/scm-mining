<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SerializedItemSeeder extends Seeder
{
    public function run(): void
    {
        // Get UOM ID for PCS
        $uomId = DB::table('uoms')->where('code', 'PCS')->value('id');

        // Create or update serialized item: Laptop
        DB::table('items')->updateOrInsert(
            ['sku' => 'ITM-LPT-001'],
            [
                'name' => 'Laptop Dell Latitude 5420',
                'is_serialized' => true,
                'criticality_level' => 1, // High criticality
                'base_uom_id' => $uomId,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
        $laptopId = DB::table('items')->where('sku', 'ITM-LPT-001')->value('id');

        // Create or update serialized item: Tablet
        DB::table('items')->updateOrInsert(
            ['sku' => 'ITM-TAB-001'],
            [
                'name' => 'Samsung Galaxy Tab A8',
                'is_serialized' => true,
                'criticality_level' => 2,
                'base_uom_id' => $uomId,
                'updated_at' => now(),
                'created_at' => now(),
            ]
        );
        $tabletId = DB::table('items')->where('sku', 'ITM-TAB-001')->value('id');

        // Get warehouse and storage location for sample serial numbers
        $warehouse = DB::table('warehouses')->where('code', 'WH-UTAMA')->first();

        if ($warehouse) {
            $storageLocation = DB::table('warehouse_locations')
                ->where('warehouse_id', $warehouse->id)
                ->where('type', 'STORAGE')
                ->where('is_active', true)
                ->first();

            if ($storageLocation) {
                // Create sample serial numbers for Laptop (5 units)
                $laptopSerials = [
                    'SN-LPT-2025-001',
                    'SN-LPT-2025-002',
                    'SN-LPT-2025-003',
                    'SN-LPT-2025-004',
                    'SN-LPT-2025-005',
                ];

                foreach ($laptopSerials as $serial) {
                    DB::table('item_serial_numbers')->insert([
                        'item_id' => $laptopId,
                        'serial_number' => $serial,
                        'status' => 'AVAILABLE',
                        'current_location_id' => $storageLocation->id,
                        'received_at' => now()->subDays(rand(1, 30)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // Create sample serial numbers for Tablet (3 units)
                $tabletSerials = [
                    'SN-TAB-2025-A01',
                    'SN-TAB-2025-A02',
                    'SN-TAB-2025-A03',
                ];

                foreach ($tabletSerials as $serial) {
                    DB::table('item_serial_numbers')->insert([
                        'item_id' => $tabletId,
                        'serial_number' => $serial,
                        'status' => 'AVAILABLE',
                        'current_location_id' => $storageLocation->id,
                        'received_at' => now()->subDays(rand(1, 20)),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $this->command->info('✅ Created 2 serialized items with 8 serial numbers');
            } else {
                $this->command->warn('⚠️  No STORAGE location found for warehouse. Skipping serial numbers.');
            }
        } else {
            $this->command->warn('⚠️  Warehouse WH-01 not found. Skipping serial numbers.');
        }
    }
}
