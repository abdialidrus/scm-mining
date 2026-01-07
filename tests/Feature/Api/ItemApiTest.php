<?php

use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\Uom;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    // Create authenticated user
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);

    // Create test UOM
    $this->uom = Uom::create([
        'code' => 'PCS',
        'name' => 'Pieces',
    ]);

    // Create test category
    $this->category = ItemCategory::create([
        'code' => 'CAT01',
        'name' => 'Test Category',
    ]);
});

test('it can list all items', function () {
    // Create test items
    Item::create([
        'sku' => 'ITEM001',
        'name' => 'Test Item 1',
        'is_serialized' => false,
        'criticality_level' => 1,
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    Item::create([
        'sku' => 'ITEM002',
        'name' => 'Test Item 2',
        'is_serialized' => true,
        'criticality_level' => 2,
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    $response = $this->getJson('/api/items');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => ['id', 'sku', 'name', 'is_serialized', 'criticality_level']
                ]
            ]
        ]);

    expect($response->json('data.data'))->toHaveCount(2);
});

test('it can view a single item', function () {
    $item = Item::create([
        'sku' => 'ITEM001',
        'name' => 'Test Item',
        'is_serialized' => false,
        'criticality_level' => 1,
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    $response = $this->getJson("/api/items/{$item->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $item->id)
        ->assertJsonPath('data.sku', 'ITEM001')
        ->assertJsonPath('data.name', 'Test Item');
});

test('it returns 404 for non-existent item', function () {
    $response = $this->getJson('/api/items/99999');

    $response->assertNotFound();
});

test('it can create a new item', function () {
    $response = $this->postJson('/api/items', [
        'sku' => 'ITEM999',
        'name' => 'New Test Item',
        'is_serialized' => false,
        'criticality_level' => 1,
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.sku', 'ITEM999')
        ->assertJsonPath('data.name', 'New Test Item');

    $this->assertDatabaseHas('items', [
        'sku' => 'ITEM999',
        'name' => 'New Test Item',
    ]);
});

test('it validates required fields when creating', function () {
    $response = $this->postJson('/api/items', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['sku', 'name', 'base_uom_id']);
});

test('it validates sku uniqueness when creating', function () {
    Item::create([
        'sku' => 'ITEM001',
        'name' => 'Test Item',
        'is_serialized' => false,
        'criticality_level' => 1,
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    $response = $this->postJson('/api/items', [
        'sku' => 'ITEM001', // Duplicate SKU
        'name' => 'Another Item',
        'base_uom_id' => $this->uom->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['sku']);
});

test('it validates base_uom_id exists when creating', function () {
    $response = $this->postJson('/api/items', [
        'sku' => 'ITEM001',
        'name' => 'Test Item',
        'base_uom_id' => 99999, // Non-existent UOM
        'item_category_id' => $this->category->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['base_uom_id']);
});

test('it validates item_category_id exists when creating', function () {
    $response = $this->postJson('/api/items', [
        'sku' => 'ITEM001',
        'name' => 'Test Item',
        'base_uom_id' => $this->uom->id,
        'item_category_id' => 99999, // Non-existent category
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['item_category_id']);
});

test('it can create a serialized item', function () {
    $response = $this->postJson('/api/items', [
        'sku' => 'SERIAL001',
        'name' => 'Serialized Item',
        'is_serialized' => true,
        'criticality_level' => 3,
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.is_serialized', true);
});

test('it can update an item', function () {
    $item = Item::create([
        'sku' => 'ITEM001',
        'name' => 'Original Name',
        'is_serialized' => false,
        'criticality_level' => 1,
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    $response = $this->putJson("/api/items/{$item->id}", [
        'sku' => 'ITEM001',
        'name' => 'Updated Name',
        'is_serialized' => false,
        'criticality_level' => 2,
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'Updated Name')
        ->assertJsonPath('data.criticality_level', 2);

    $this->assertDatabaseHas('items', [
        'id' => $item->id,
        'name' => 'Updated Name',
    ]);
});

test('it validates sku uniqueness when updating (except self)', function () {
    $item1 = Item::create([
        'sku' => 'ITEM001',
        'name' => 'Item 1',
        'is_serialized' => false,
        'criticality_level' => 1,
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    $item2 = Item::create([
        'sku' => 'ITEM002',
        'name' => 'Item 2',
        'is_serialized' => false,
        'criticality_level' => 1,
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    // Try to update item2 with item1's SKU
    $response = $this->putJson("/api/items/{$item2->id}", [
        'sku' => 'ITEM001', // Already used by item1
        'name' => 'Item 2',
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['sku']);
});

test('it allows keeping same sku when updating', function () {
    $item = Item::create([
        'sku' => 'ITEM001',
        'name' => 'Test Item',
        'is_serialized' => false,
        'criticality_level' => 1,
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    $response = $this->putJson("/api/items/{$item->id}", [
        'sku' => 'ITEM001', // Same SKU
        'name' => 'Updated Item',
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
        'criticality_level' => 1,
    ]);

    $response->assertOk();
});

test('it can change item category', function () {
    $category2 = ItemCategory::create([
        'code' => 'CAT02',
        'name' => 'Another Category',
    ]);

    $item = Item::create([
        'sku' => 'ITEM001',
        'name' => 'Test Item',
        'is_serialized' => false,
        'criticality_level' => 1,
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    $response = $this->putJson("/api/items/{$item->id}", [
        'sku' => 'ITEM001',
        'name' => 'Test Item',
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $category2->id,
        'criticality_level' => 1,
    ]);

    $response->assertOk()
        ->assertJsonPath('data.item_category_id', $category2->id);
});

test('it can delete an item', function () {
    $item = Item::create([
        'sku' => 'TEMP001',
        'name' => 'Temporary Item',
        'is_serialized' => false,
        'criticality_level' => 1,
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    $response = $this->deleteJson("/api/items/{$item->id}");

    $response->assertOk();

    $this->assertDatabaseMissing('items', [
        'id' => $item->id,
    ]);
});

test('it returns 404 when deleting non-existent item', function () {
    $response = $this->deleteJson('/api/items/99999');

    $response->assertNotFound();
});

test('item includes relationships when loaded', function () {
    $item = Item::create([
        'sku' => 'ITEM001',
        'name' => 'Test Item',
        'is_serialized' => false,
        'criticality_level' => 1,
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    $response = $this->getJson("/api/items/{$item->id}");

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'sku',
                'name',
                'is_serialized',
                'criticality_level',
                'base_uom_id',
                'item_category_id',
                'base_uom' => ['id', 'code', 'name'],
                'category' => ['id', 'code', 'name'],
            ]
        ]);
});

test('it can search items by sku', function () {
    Item::create([
        'sku' => 'COMP001',
        'name' => 'Computer',
        'is_serialized' => false,
        'criticality_level' => 1,
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    Item::create([
        'sku' => 'DESK001',
        'name' => 'Desk',
        'is_serialized' => false,
        'criticality_level' => 1,
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    $response = $this->getJson('/api/items?search=COMP');

    $response->assertOk();

    $items = $response->json('data.data');
    expect($items)->toHaveCount(1);
    expect($items[0]['sku'])->toBe('COMP001');
});

test('it can search items by name', function () {
    Item::create([
        'sku' => 'ITEM001',
        'name' => 'Laptop Computer',
        'is_serialized' => false,
        'criticality_level' => 1,
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    Item::create([
        'sku' => 'ITEM002',
        'name' => 'Office Desk',
        'is_serialized' => false,
        'criticality_level' => 1,
        'base_uom_id' => $this->uom->id,
        'item_category_id' => $this->category->id,
    ]);

    $response = $this->getJson('/api/items?search=Laptop');

    $response->assertOk();

    $items = $response->json('data.data');
    expect($items)->toHaveCount(1);
    expect($items[0]['name'])->toBe('Laptop Computer');
});
