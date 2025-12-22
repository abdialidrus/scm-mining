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
        ];

        foreach ($uoms as $uom) {
            Uom::query()->updateOrCreate(
                ['code' => $uom['code']],
                ['name' => $uom['name']],
            );
        }
    }
}
