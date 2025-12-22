<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemSeeder extends Seeder
{
    public function run(): void
    {
        $items = [
            [
                'item_code' => 'ITM-EO-001',
                'item_name' => 'Engine Oil SAE 15W-40',
                'uom' => 'LTR',
                'is_serialized' => false,
                'is_batch_tracked' => true,
                'criticality_level' => 'MEDIUM',
            ],
            [
                'item_code' => 'ITM-FF-001',
                'item_name' => 'Fuel Filter (Heavy Duty)',
                'uom' => 'PCS',
                'is_serialized' => false,
                'is_batch_tracked' => false,
                'criticality_level' => 'MEDIUM',
            ],
            [
                'item_code' => 'ITM-HH-001',
                'item_name' => 'Hydraulic Hose 1/2" 2-Wire',
                'uom' => 'MTR',
                'is_serialized' => false,
                'is_batch_tracked' => false,
                'criticality_level' => 'HIGH',
            ],
        ];

        foreach ($items as $item) {
            DB::table('items')->updateOrInsert(
                ['item_code' => $item['item_code']],
                [
                    'item_name' => $item['item_name'],
                    'uom' => $item['uom'], // legacy string UOM (kept intentionally)
                    'is_serialized' => $item['is_serialized'],
                    'is_batch_tracked' => $item['is_batch_tracked'],
                    'criticality_level' => $item['criticality_level'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }
}
