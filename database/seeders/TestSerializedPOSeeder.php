<?php

namespace Database\Seeders;

use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\Supplier;
use App\Models\Uom;
use Illuminate\Database\Seeder;

class TestSerializedPOSeeder extends Seeder
{
    public function run(): void
    {
        $supplier = Supplier::first();
        $laptop = Item::where('sku', 'ITM-LPT-001')->first();
        $uom = Uom::where('code', 'PCS')->first();

        if (!$supplier || !$laptop || !$uom) {
            $this->command->error('Missing required data (Supplier, Laptop, or UOM)');
            return;
        }

        // Create PO for testing serial numbers
        $po = PurchaseOrder::create([
            'po_number' => 'PO-TEST-SERIAL-001',
            'supplier_id' => $supplier->id,
            'status' => PurchaseOrder::STATUS_SENT,
            'currency_code' => 'IDR',
            'tax_rate' => 0.11,
            'subtotal_amount' => 30000000,
            'tax_amount' => 3300000,
            'total_amount' => 33300000,
            'supplier_snapshot' => [
                'id' => $supplier->id,
                'code' => $supplier->code,
                'name' => $supplier->name,
            ],
            'tax_snapshot' => ['rate' => 0.11],
            'totals_snapshot' => [
                'subtotal' => 30000000,
                'tax' => 3300000,
                'total' => 33300000,
            ],
            'sent_at' => now(),
        ]);

        PurchaseOrderLine::create([
            'purchase_order_id' => $po->id,
            'line_no' => 1,
            'item_id' => $laptop->id,
            'quantity' => 3,
            'uom_id' => $uom->id,
            'unit_price' => 10000000,
            'item_snapshot' => [
                'id' => $laptop->id,
                'sku' => $laptop->sku,
                'name' => $laptop->name,
                'is_serialized' => $laptop->is_serialized,
            ],
            'uom_snapshot' => [
                'id' => $uom->id,
                'code' => $uom->code,
                'name' => $uom->name,
            ],
        ]);

        $this->command->info("✅ Created PO: {$po->po_number} (ID: {$po->id}) with 3 Laptops (serialized)");
    }
}
