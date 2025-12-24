<?php

declare(strict_types=1);

use App\Models\GoodsReceipt;
use App\Models\GoodsReceiptLine;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderLine;
use App\Models\PutAway;
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

it('posts put away and moves stock from RECEIVING to STORAGE; GR becomes PUT_AWAY_COMPLETED', function () {
    $uom = Uom::query()->create(['code' => 'EA-PA', 'name' => 'Each']);
    $item = Item::query()->create([
        'sku' => 'IT-PA-01',
        'name' => 'Put Away Item',
        'base_uom_id' => $uom->id,
    ]);

    $supplier = Supplier::query()->create([
        'code' => 'SUP-PA-01',
        'name' => 'Put Away Supplier',
    ]);

    $warehouse = Warehouse::query()->create([
        'code' => 'WH-PA-01',
        'name' => 'WH PutAway',
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

    $storage = WarehouseLocation::query()->create([
        'warehouse_id' => $warehouse->id,
        'parent_id' => null,
        'type' => WarehouseLocation::TYPE_STORAGE,
        'code' => 'STO-A',
        'name' => 'Storage A',
        'is_default' => false,
        'is_active' => true,
    ]);

    $po = PurchaseOrder::query()->create([
        'po_number' => 'PO-PA-01',
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

    Role::findOrCreate('warehouse');
    $warehouseUser = User::factory()->create();
    $warehouseUser->assignRole('warehouse');
    Sanctum::actingAs($warehouseUser);

    $createGr = postJson('/api/goods-receipts', [
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

    $grId = (int) $createGr->json('data.id');

    postJson("/api/goods-receipts/{$grId}/post", [])->assertOk();

    /** @var GoodsReceiptLine $grLine */
    $grLine = GoodsReceiptLine::query()->where('goods_receipt_id', $grId)->firstOrFail();

    // Create Put Away draft
    $createPa = postJson('/api/put-aways', [
        'goods_receipt_id' => $grId,
        'put_away_at' => now()->toISOString(),
        'remarks' => 'pa test',
        'lines' => [
            [
                'goods_receipt_line_id' => $grLine->id,
                'destination_location_id' => $storage->id,
                'qty' => 2,
                'remarks' => null,
            ],
        ],
    ])->assertCreated();

    $paId = (int) $createPa->json('data.id');

    // Post Put Away
    postJson("/api/put-aways/{$paId}/post", [])
        ->assertOk()
        ->assertJsonPath('data.status', PutAway::STATUS_POSTED);

    // Ledger should now have 2 movements: inbound GR, and transfer put away.
    assertDatabaseCount('stock_movements', 2);

    assertDatabaseHas('stock_movements', [
        'reference_type' => StockMovement::REF_PUT_AWAY,
        'reference_id' => $paId,
        'source_location_id' => $receiving->id,
        'destination_location_id' => $storage->id,
        'item_id' => $item->id,
        'uom_id' => $uom->id,
    ]);

    // GR should become PUT_AWAY_COMPLETED
    assertDatabaseHas('goods_receipts', [
        'id' => $grId,
        'status' => GoodsReceipt::STATUS_PUT_AWAY_COMPLETED,
    ]);
});

it('rejects put away draft creation when GR has no remaining qty (stale UI guard)', function () {
    $uom = Uom::query()->create(['code' => 'EA-PA2', 'name' => 'Each']);
    $item = Item::query()->create([
        'sku' => 'IT-PA-02',
        'name' => 'Put Away Item 2',
        'base_uom_id' => $uom->id,
    ]);

    $supplier = Supplier::query()->create([
        'code' => 'SUP-PA-02',
        'name' => 'Put Away Supplier 2',
    ]);

    $warehouse = Warehouse::query()->create([
        'code' => 'WH-PA-02',
        'name' => 'WH PutAway 2',
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

    $storage = WarehouseLocation::query()->create([
        'warehouse_id' => $warehouse->id,
        'parent_id' => null,
        'type' => WarehouseLocation::TYPE_STORAGE,
        'code' => 'STO-A',
        'name' => 'Storage A',
        'is_default' => false,
        'is_active' => true,
    ]);

    $po = PurchaseOrder::query()->create([
        'po_number' => 'PO-PA-02',
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

    Role::findOrCreate('warehouse');
    $warehouseUser = User::factory()->create();
    $warehouseUser->assignRole('warehouse');
    Sanctum::actingAs($warehouseUser);

    // Create & POST GR with received qty = 2
    $createGr = postJson('/api/goods-receipts', [
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

    $grId = (int) $createGr->json('data.id');
    postJson("/api/goods-receipts/{$grId}/post", [])->assertOk();

    /** @var GoodsReceiptLine $grLine */
    $grLine = GoodsReceiptLine::query()->where('goods_receipt_id', $grId)->firstOrFail();

    // Create & POST a Put Away for full qty=2 (so remaining becomes 0)
    $createPa1 = postJson('/api/put-aways', [
        'goods_receipt_id' => $grId,
        'put_away_at' => now()->toISOString(),
        'remarks' => 'pa test',
        'lines' => [
            [
                'goods_receipt_line_id' => $grLine->id,
                'destination_location_id' => $storage->id,
                'qty' => 2,
                'remarks' => null,
            ],
        ],
    ])->assertCreated();

    $pa1Id = (int) $createPa1->json('data.id');
    postJson("/api/put-aways/{$pa1Id}/post", [])->assertOk();

    // Simulate stale UI / race condition:
    // Some client might still think this GR is eligible (POSTED/PUT_AWAY_PARTIAL),
    // but in reality it is already fully put away.
    // The service should still reject based on remaining qty.
    GoodsReceipt::query()->whereKey($grId)->update([
        'status' => GoodsReceipt::STATUS_PUT_AWAY_PARTIAL,
    ]);

    // Attempt to create another draft for the same GR line should be rejected.
    $res = postJson('/api/put-aways', [
        'goods_receipt_id' => $grId,
        'put_away_at' => now()->toISOString(),
        'remarks' => 'stale ui',
        'lines' => [
            [
                'goods_receipt_line_id' => $grLine->id,
                'destination_location_id' => $storage->id,
                'qty' => 1,
                'remarks' => null,
            ],
        ],
    ])->assertUnprocessable();

    $payload = $res->json();
    expect($payload['errors']['lines.0.goods_receipt_line_id'][0] ?? null)
        ->toBe('No remaining quantity for this GR line.');
});

it('rejects put away draft creation when qty exceeds remaining qty for a GR line', function () {
    $uom = Uom::query()->create(['code' => 'EA-PA3', 'name' => 'Each']);
    $item = Item::query()->create([
        'sku' => 'IT-PA-03',
        'name' => 'Put Away Item 3',
        'base_uom_id' => $uom->id,
    ]);

    $supplier = Supplier::query()->create([
        'code' => 'SUP-PA-03',
        'name' => 'Put Away Supplier 3',
    ]);

    $warehouse = Warehouse::query()->create([
        'code' => 'WH-PA-03',
        'name' => 'WH PutAway 3',
        'address' => null,
        'is_active' => true,
    ]);

    WarehouseLocation::query()->create([
        'warehouse_id' => $warehouse->id,
        'parent_id' => null,
        'type' => WarehouseLocation::TYPE_RECEIVING,
        'code' => 'RCV',
        'name' => 'Receiving',
        'is_default' => true,
        'is_active' => true,
    ]);

    $storage = WarehouseLocation::query()->create([
        'warehouse_id' => $warehouse->id,
        'parent_id' => null,
        'type' => WarehouseLocation::TYPE_STORAGE,
        'code' => 'STO-A',
        'name' => 'Storage A',
        'is_default' => false,
        'is_active' => true,
    ]);

    $po = PurchaseOrder::query()->create([
        'po_number' => 'PO-PA-03',
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

    Role::findOrCreate('warehouse');
    $warehouseUser = User::factory()->create();
    $warehouseUser->assignRole('warehouse');
    Sanctum::actingAs($warehouseUser);

    // Create & POST GR with received qty = 2
    $createGr = postJson('/api/goods-receipts', [
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

    $grId = (int) $createGr->json('data.id');
    postJson("/api/goods-receipts/{$grId}/post", [])->assertOk();

    /** @var GoodsReceiptLine $grLine */
    $grLine = GoodsReceiptLine::query()->where('goods_receipt_id', $grId)->firstOrFail();

    $res = postJson('/api/put-aways', [
        'goods_receipt_id' => $grId,
        'put_away_at' => now()->toISOString(),
        'remarks' => 'over remaining',
        'lines' => [
            [
                'goods_receipt_line_id' => $grLine->id,
                'destination_location_id' => $storage->id,
                'qty' => 3,
                'remarks' => null,
            ],
        ],
    ])->assertUnprocessable();

    $payload = $res->json();
    expect($payload['errors']['lines.0.qty'][0] ?? null)
        ->toContain('Qty exceeds remaining quantity.');
});
