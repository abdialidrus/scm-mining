<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\ItemInventorySetting;
use App\Models\Warehouse;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class ItemInventorySettingSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get all items and warehouses
        $items = Item::all();
        $warehouses = Warehouse::all();

        if ($items->isEmpty() || $warehouses->isEmpty()) {
            $this->command->warn('No items or warehouses found. Please seed them first.');
            return;
        }

        // Create settings for each item in each warehouse
        foreach ($items as $item) {
            foreach ($warehouses as $warehouse) {
                // Skip if setting already exists
                if (ItemInventorySetting::where('item_id', $item->id)
                    ->where('warehouse_id', $warehouse->id)
                    ->exists()
                ) {
                    continue;
                }

                // Create random but realistic settings
                $baseReorderPoint = rand(10, 100);

                ItemInventorySetting::create([
                    'item_id' => $item->id,
                    'warehouse_id' => $warehouse->id,
                    'reorder_point' => $baseReorderPoint,
                    'reorder_quantity' => $baseReorderPoint * rand(2, 5), // 2-5x reorder point
                    'min_stock' => (int)($baseReorderPoint * 0.5), // 50% of reorder point
                    'max_stock' => $baseReorderPoint * rand(8, 15), // 8-15x reorder point
                    'lead_time_days' => rand(3, 30),
                    'safety_stock' => (int)($baseReorderPoint * 0.3), // 30% of reorder point
                    'is_active' => true,
                    'notes' => null,
                ]);
            }
        }

        $this->command->info('Created inventory settings for ' . ($items->count() * $warehouses->count()) . ' item-warehouse combinations.');
    }
}
