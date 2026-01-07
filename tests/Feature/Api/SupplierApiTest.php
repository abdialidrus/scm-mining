<?php

use App\Models\Supplier;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    // Create role if not exists
    Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

    $this->user = User::factory()->create();
    $this->user->assignRole('super_admin');
    Sanctum::actingAs($this->user);
});

test('it can list all suppliers', function () {
    Supplier::create([
        'code' => 'SUP001',
        'name' => 'ABC Corporation',
        'contact_name' => 'John Doe',
        'phone' => '123456789',
        'email' => 'contact@abc.com',
        'address' => '123 Main St',
    ]);

    Supplier::create([
        'code' => 'SUP002',
        'name' => 'XYZ Limited',
        'contact_name' => 'Jane Smith',
        'phone' => '987654321',
        'email' => 'info@xyz.com',
        'address' => '456 Oak Ave',
    ]);

    $response = $this->getJson('/api/suppliers');

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'data' => [
                    '*' => ['id', 'code', 'name', 'contact_name', 'phone', 'email', 'address', 'created_at', 'updated_at']
                ]
            ]
        ]);

    $suppliers = $response->json('data.data');
    expect($suppliers)->toHaveCount(2);
});

test('it can view a single supplier', function () {
    $supplier = Supplier::create([
        'code' => 'SUP001',
        'name' => 'ABC Corporation',
        'contact_name' => 'John Doe',
        'phone' => '123456789',
        'email' => 'contact@abc.com',
        'address' => '123 Main St',
    ]);

    $response = $this->getJson("/api/suppliers/{$supplier->id}");

    $response->assertOk()
        ->assertJsonPath('data.id', $supplier->id)
        ->assertJsonPath('data.code', 'SUP001')
        ->assertJsonPath('data.name', 'ABC Corporation')
        ->assertJsonPath('data.contact_name', 'John Doe');
});

test('it returns 404 for non-existent supplier', function () {
    $response = $this->getJson('/api/suppliers/99999');

    $response->assertNotFound();
});

test('it can create a new supplier', function () {
    $response = $this->postJson('/api/suppliers', [
        'name' => 'ABC Corporation',
        'contact_name' => 'John Doe',
        'phone' => '123456789',
        'email' => 'contact@abc.com',
        'address' => '123 Main St',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'ABC Corporation')
        ->assertJsonPath('data.contact_name', 'John Doe');

    $this->assertDatabaseHas('suppliers', [
        'name' => 'ABC Corporation',
    ]);
});

test('it can create a supplier with minimal data', function () {
    $response = $this->postJson('/api/suppliers', [
        'name' => 'Minimal Supplier',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Minimal Supplier');
});

test('it validates required fields when creating', function () {
    $response = $this->postJson('/api/suppliers', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('it validates email format when creating', function () {
    $response = $this->postJson('/api/suppliers', [
        'name' => 'Test Supplier',
        'email' => 'invalid-email',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('it can update a supplier', function () {
    $supplier = Supplier::create([
        'code' => 'SUP001',
        'name' => 'ABC Corporation',
        'contact_name' => 'John Doe',
        'phone' => '123456789',
        'email' => 'contact@abc.com',
        'address' => '123 Main St',
    ]);

    $response = $this->putJson("/api/suppliers/{$supplier->id}", [
        'name' => 'ABC Corporation Updated',
        'contact_name' => 'Jane Doe',
        'phone' => '111222333',
        'email' => 'newcontact@abc.com',
        'address' => '456 New St',
    ]);

    $response->assertOk()
        ->assertJsonPath('data.name', 'ABC Corporation Updated')
        ->assertJsonPath('data.contact_name', 'Jane Doe')
        ->assertJsonPath('data.phone', '111222333');

    $this->assertDatabaseHas('suppliers', [
        'id' => $supplier->id,
        'name' => 'ABC Corporation Updated',
        'contact_name' => 'Jane Doe',
    ]);
});

test('it can update supplier name only', function () {
    $supplier = Supplier::create([
        'code' => 'SUP001',
        'name' => 'Original Supplier Name',
    ]);

    $response = $this->putJson("/api/suppliers/{$supplier->id}", [
        'name' => 'Updated Supplier Name',
    ]);

    $response->assertOk();
    expect($response->json('data.name'))->toBe('Updated Supplier Name');
});

test('it can delete a supplier', function () {
    $supplier = Supplier::create([
        'code' => 'SUP999',
        'name' => 'Temporary Supplier',
    ]);

    $response = $this->deleteJson("/api/suppliers/{$supplier->id}");

    $response->assertOk();

    $this->assertDatabaseMissing('suppliers', [
        'id' => $supplier->id,
    ]);
});

test('it returns 404 when deleting non-existent supplier', function () {
    $response = $this->deleteJson('/api/suppliers/99999');

    $response->assertNotFound();
});

test('it can search suppliers by code', function () {
    Supplier::create([
        'code' => 'ABC001',
        'name' => 'ABC Corporation',
    ]);

    Supplier::create([
        'code' => 'XYZ002',
        'name' => 'XYZ Limited',
    ]);

    Supplier::create([
        'code' => 'ABC003',
        'name' => 'Another ABC Company',
    ]);

    $response = $this->getJson('/api/suppliers?search=ABC');

    $response->assertOk();

    $suppliers = $response->json('data.data');
    expect($suppliers)->toHaveCount(2);
    expect($suppliers[0]['code'])->toContain('ABC');
});

test('it can search suppliers by name', function () {
    Supplier::create([
        'code' => 'SUP001',
        'name' => 'Technology Solutions Inc',
    ]);

    Supplier::create([
        'code' => 'SUP002',
        'name' => 'Hardware Supplies Ltd',
    ]);

    Supplier::create([
        'code' => 'SUP003',
        'name' => 'Tech Innovations Corp',
    ]);

    $response = $this->getJson('/api/suppliers?search=Tech');

    $response->assertOk();

    $suppliers = $response->json('data.data');
    expect($suppliers)->toHaveCount(2);
});
