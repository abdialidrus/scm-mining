<?php

namespace Database\Seeders;

use App\Models\Supplier;
use App\Services\Supplier\SupplierCodeGenerator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        /** @var array<int, array{name:string,contact_name?:string|null,phone?:string|null,email?:string|null,address?:string|null}> $rows */
        $rows = [
            [
                'name' => 'PT Sumber Makmur Mining Supply',
                'contact_name' => 'Budi Santoso',
                'phone' => '+62 811-1111-1111',
                'email' => 'sales@sumbermakmur.example',
                'address' => 'Jl. Gatot Subroto No. 123, Jakarta',
            ],
            [
                'name' => 'CV Anugerah Teknik',
                'contact_name' => 'Siti Rahma',
                'phone' => '+62 812-2222-2222',
                'email' => 'admin@anugerahteknik.example',
                'address' => 'Jl. Raya Bekasi KM 21, Bekasi',
            ],
            [
                'name' => 'PT Borneo Parts & Services',
                'contact_name' => 'Agus Pranoto',
                'phone' => '+62 813-3333-3333',
                'email' => 'cs@borneoparts.example',
                'address' => 'Samarinda, Kalimantan Timur',
            ],
            [
                'name' => 'PT Nusantara Chemical',
                'contact_name' => 'Dewi Lestari',
                'phone' => '+62 814-4444-4444',
                'email' => 'contact@nusantarachem.example',
                'address' => 'Gresik Industrial Estate, Jawa Timur',
            ],
            [
                'name' => 'PT Mandiri Logistics',
                'contact_name' => 'Rizky Hidayat',
                'phone' => '+62 815-5555-5555',
                'email' => 'hello@mandirilogistics.example',
                'address' => 'Balikpapan, Kalimantan Timur',
            ],
        ];

        DB::transaction(function () use ($rows) {
            $generator = app(SupplierCodeGenerator::class);

            foreach ($rows as $row) {
                $name = trim((string) $row['name']);

                /** @var Supplier $supplier */
                $supplier = Supplier::query()->firstOrNew(['name' => $name]);

                if (!$supplier->exists) {
                    $supplier->code = $generator->generate();
                }

                $supplier->contact_name = Arr::get($row, 'contact_name');
                $supplier->phone = Arr::get($row, 'phone');
                $supplier->email = Arr::get($row, 'email');
                $supplier->address = Arr::get($row, 'address');
                $supplier->save();
            }
        });
    }
}
