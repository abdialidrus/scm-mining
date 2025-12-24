<?php

declare(strict_types=1);

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Uom;
use App\Models\User;
use App\Models\Warehouse;
use App\Models\WarehouseLocation;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

it('creates stock movements into default RECEIVING location when GR is posted', function () {
    // master data
    $uom = Uom::query()->create(['code' => 'EA', 'name' => 'Each']);
    $item = Item::query()->create([
        'sku' => 'IT-GR-01',
        'name' => 'Test Item',
        'base_uom_id' => $uom->id,
    ]);

    $supplier = Supplier::query()->create([
        'code' => 'SUP-01',
        'name' => 'Test Supplier',
    ]);

    $warehouse = Warehouse::query()->create([
        'code' => 'WH-01',
        'name' => 'Main WH',
        'address' => null,
        'is_active' => true,
    ]);

    $receiving = WarehouseLocation::query()->create([
        'warehouse_id' => $warehouse->id,
        'parent_id' => null,
        'type' => WarehouseLocation::TYPE_RECEIVING,
        'code' => 'RCV',
        'name' => 'Receiving',
        'is_default' => true,
        'is_active' => true,
    ]);

    // PO
    $po = PurchaseOrder::query()->create([
        'po_number' => 'PO-TEST-01',
        'supplier_id' => $supplier->id,
        'status' => PurchaseOrder::STATUS_APPROVED,
        'currency_code' => 'IDR',
        'tax_rate' => 0,
        'supplier_snapshot' => ['id' => $supplier->id, 'code' => $supplier->code, 'name' => $supplier->name],
        'totals_snapshot' => ['subtotal' => 0, 'tax' => 0, 'grand_total' => 0],
    ]);

    $poLine = PurchaseOrderLine::query()->create([
        'purchase_order_id' => $po->id,
        'line_no' => 1,
        'item_id' => $item->id,
        'uom_id' => $uom->id,
        'quantity' => 5,
        'unit_price' => 1000,
        'item_snapshot' => ['id' => $item->id, 'sku' => $item->sku, 'name' => $item->name],
        'uom_snapshot' => ['id' => $uom->id, 'code' => $uom->code, 'name' => $uom->name],
        'remarks' => null,
    ]);

    // warehouse actor
    Role::findOrCreate('warehouse');
    $warehouseUser = User::factory()->create();
    $warehouseUser->assignRole('warehouse');
    Sanctum::actingAs($warehouseUser);

    // Create GR draft via API
    $create = postJson('/api/goods-receipts', [
        'purchase_order_id' => $po->id,
        'warehouse_id' => $warehouse->id,
        'received_at' => now()->toISOString(),
        'remarks' => 'test',
        'lines' => [
            [
                'purchase_order_line_id' => $poLine->id,
                'received_quantity' => 2,
                'remarks' => null,
            ],
        ],
    ])->assertCreated();

    $grId = (int) $create->json('data.id');

    // Sanity (draft)
    $gr = GoodsReceipt::query()->with('lines')->findOrFail($grId);
    expect($gr->status)->toBe(GoodsReceipt::STATUS_DRAFT);
    expect($gr->lines)->toHaveCount(1);

    // Post GR
    postJson("/api/goods-receipts/{$grId}/post", [])
        ->assertOk()
        ->assertJsonPath('data.status', GoodsReceipt::STATUS_POSTED);

    // Assert stock movement created
    assertDatabaseCount('stock_movements', 1);

    /** @var GoodsReceiptLine $grLine */
    $grLine = GoodsReceiptLine::query()->where('goods_receipt_id', $grId)->firstOrFail();

    assertDatabaseHas('stock_movements', [
        'item_id' => $item->id,
        'uom_id' => $uom->id,
        'source_location_id' => null,
        'destination_location_id' => $receiving->id,
        'reference_type' => StockMovement::REF_GOODS_RECEIPT,
        'reference_id' => $grId,
    ]);
});
