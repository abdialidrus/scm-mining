<?php

namespace Database\Seeders;

use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['code' => 'WH-MAIN', 'name' => 'Main Warehouse', 'address' => null, 'is_active' => true],
            ['code' => 'WH-SITE', 'name' => 'Site Warehouse', 'address' => null, 'is_active' => true],
        ];

        foreach ($rows as $row) {
            Warehouse::query()->updateOrCreate(
                ['code' => $row['code']],
                [
                    'name' => $row['name'],
                    'address' => $row['address'],
                    'is_active' => $row['is_active'],
                ],
            );
        }
    }
}
