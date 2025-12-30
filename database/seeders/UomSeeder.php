<?php

namespace Database\Seeders;

use App\Models\Uom;
use Illuminate\Database\Seeder;

class UomSeeder extends Seeder
{
    public function run(): void
    {
        $uoms = [
            ['code' => 'PCS', 'name' => 'Pieces'],
            ['code' => 'BOX', 'name' => 'Box'],
            ['code' => 'KG', 'name' => 'Kilogram'],
            ['code' => 'LTR', 'name' => 'Liter'],
            ['code' => 'MTR', 'name' => 'Meter'],
            ['code' => 'SET', 'name' => 'Set'],
            ['code' => 'PAIR', 'name' => 'Pair'],
            ['code' => 'CAN', 'name' => 'Can'],
            ['code' => 'BTL', 'name' => 'Bottle'],
            ['code' => 'RIM', 'name' => 'Ream'],
            ['code' => 'BAG', 'name' => 'Bag'],
            ['code' => 'BTG', 'name' => 'Batang'],
            ['code' => 'SHT', 'name' => 'Sheet'],
        ];

        foreach ($uoms as $uom) {
            Uom::query()->updateOrCreate(
                ['code' => $uom['code']],
                ['name' => $uom['name']],
            );
        }

        $this->command->info('✅ ' . count($uoms) . ' UOMs seeded successfully');
    }
}
