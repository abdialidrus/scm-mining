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
                'sku' => 'ITM-EO-001',
                'name' => 'Engine Oil SAE 15W-40',
                'uom_code' => 'LTR',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'ITM-FF-001',
                'name' => 'Fuel Filter (Heavy Duty)',
                'uom_code' => 'PCS',
                'is_serialized' => false,
                'criticality_level' => 3,
            ],
            [
                'sku' => 'ITM-HH-001',
                'name' => 'Hydraulic Hose 1/2" 2-Wire',
                'uom_code' => 'MTR',
                'is_serialized' => false,
                'criticality_level' => 2,
            ],
        ];

        $uomIdsByCode = DB::table('uoms')->pluck('id', 'code');

        foreach ($items as $item) {
            $baseUomId = $uomIdsByCode[$item['uom_code']] ?? null;

            DB::table('items')->updateOrInsert(
                ['sku' => $item['sku']],
                [
                    'name' => $item['name'],
                    'is_serialized' => $item['is_serialized'],
                    'criticality_level' => $item['criticality_level'],
                    'base_uom_id' => $baseUomId,
                    'updated_at' => now(),
                    'created_at' => now(),
                ],
            );
        }
    }
}
