<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    // Items
    Route::get('/master-data/items', function () {
        return Inertia::render('master-data/items/Index');
    })->name('master-data.items.index');

    Route::get('/master-data/items/create', function () {
        return Inertia::render('master-data/items/Form', [
            'id' => null,
        ]);
    })->name('master-data.items.create');

    Route::get('/master-data/items/{item}/edit', function (\App\Models\Item $item) {
        return Inertia::render('master-data/items/Form', [
            'id' => $item->id,
        ]);
    })->name('master-data.items.edit');

    Route::get('/master-data/items/{item}', function (\App\Models\Item $item) {
        return Inertia::render('master-data/items/Show', [
            'id' => $item->id,
        ]);
    })->name('master-data.items.show');

    // Item Categories
    Route::get('/master-data/item-categories', function () {
        return Inertia::render('master-data/item-categories/Index');
    })->name('master-data.item-categories.index');

    Route::get('/master-data/item-categories/create', function () {
        return Inertia::render('master-data/item-categories/Form', [
            'id' => null,
        ]);
    })->name('master-data.item-categories.create');

    Route::get('/master-data/item-categories/{itemCategory}/edit', function (\App\Models\ItemCategory $itemCategory) {
        return Inertia::render('master-data/item-categories/Form', [
            'id' => $itemCategory->id,
        ]);
    })->name('master-data.item-categories.edit');

    Route::get('/master-data/item-categories/{itemCategory}', function (\App\Models\ItemCategory $itemCategory) {
        return Inertia::render('master-data/item-categories/Show', [
            'id' => $itemCategory->id,
        ]);
    })->name('master-data.item-categories.show');

    Route::get('/master-data/uoms', function () {
        return Inertia::render('master-data/uoms/Index');
    })->name('master-data.uoms.index');

    Route::get('/master-data/departments', function () {
        return Inertia::render('master-data/departments/Index');
    })->name('master-data.departments.index');

    Route::get('/master-data/departments/create', function () {
        return Inertia::render('master-data/departments/Form', [
            'departmentId' => null,
        ]);
    })->name('master-data.departments.create');

    Route::get('/master-data/departments/{department}/edit', function (\App\Models\Department $department) {
        return Inertia::render('master-data/departments/Form', [
            'departmentId' => $department->id,
        ]);
    })->name('master-data.departments.edit');

    Route::get('/master-data/departments/{department}', function (\App\Models\Department $department) {
        return Inertia::render('master-data/departments/Show', [
            'departmentId' => $department->id,
        ]);
    })->name('master-data.departments.show');

    Route::get('/master-data/users', function () {
        return Inertia::render('master-data/users/Index');
    })->name('master-data.users.index');

    Route::get('/master-data/users/create', function () {
        return Inertia::render('master-data/users/Form', [
            'userId' => null,
        ]);
    })->name('master-data.users.create');

    Route::get('/master-data/users/{user}/edit', function (\App\Models\User $user) {
        return Inertia::render('master-data/users/Form', [
            'userId' => $user->id,
        ]);
    })->name('master-data.users.edit');

    Route::get('/master-data/users/{user}', function (\App\Models\User $user) {
        return Inertia::render('master-data/users/Show', [
            'userId' => $user->id,
        ]);
    })->name('master-data.users.show');

    Route::get('/master-data/suppliers', function () {
        return Inertia::render('master-data/suppliers/Index');
    })->name('master-data.suppliers.index');

    Route::get('/master-data/suppliers/create', function () {
        return Inertia::render('master-data/suppliers/Form', [
            'supplierId' => null,
        ]);
    })->name('master-data.suppliers.create');

    Route::get('/master-data/suppliers/{supplierId}/edit', function (int $supplierId) {
        return Inertia::render('master-data/suppliers/Form', [
            'supplierId' => $supplierId,
        ]);
    })->name('master-data.suppliers.edit');

    Route::get('/master-data/suppliers/{supplierId}', function (int $supplierId) {
        return Inertia::render('master-data/suppliers/Show', [
            'supplierId' => $supplierId,
        ]);
    })->name('master-data.suppliers.show');

    Route::get('/master-data/warehouses', function () {
        return Inertia::render('master-data/warehouses/Index');
    })->name('master-data.warehouses.index');

    Route::get('/master-data/warehouses/create', function () {
        return Inertia::render('master-data/warehouses/Form', [
            'warehouseId' => null,
        ]);
    })->name('master-data.warehouses.create');

    Route::get('/master-data/warehouses/{warehouse}/edit', function (\App\Models\Warehouse $warehouse) {
        return Inertia::render('master-data/warehouses/Form', [
            'warehouseId' => $warehouse->id,
        ]);
    })->name('master-data.warehouses.edit');

    Route::get('/master-data/warehouses/{warehouse}', function (\App\Models\Warehouse $warehouse) {
        return Inertia::render('master-data/warehouses/Show', [
            'warehouseId' => $warehouse->id,
        ]);
    })->name('master-data.warehouses.show');

    // Warehouse Locations
    Route::get('/master-data/warehouse-locations', function () {
        return Inertia::render('master-data/warehouse-locations/Index');
    })->name('master-data.warehouse-locations.index');

    Route::get('/master-data/warehouse-locations/create', function () {
        return Inertia::render('master-data/warehouse-locations/Form', [
            'locationId' => null,
        ]);
    })->name('master-data.warehouse-locations.create');

    Route::get('/master-data/warehouse-locations/{warehouse_location}/edit', function (\App\Models\WarehouseLocation $warehouse_location) {
        return Inertia::render('master-data/warehouse-locations/Form', [
            'locationId' => $warehouse_location->id,
        ]);
    })->name('master-data.warehouse-locations.edit');

    Route::get('/master-data/warehouse-locations/{warehouse_location}', function (\App\Models\WarehouseLocation $warehouse_location) {
        return Inertia::render('master-data/warehouse-locations/Show', [
            'locationId' => $warehouse_location->id,
        ]);
    })->name('master-data.warehouse-locations.show');

    // Item Inventory Settings
    Route::get('/master-data/item-inventory-settings', [\App\Http\Controllers\ItemInventorySettingController::class, 'index'])
        ->name('item-inventory-settings.index');

    Route::get('/master-data/item-inventory-settings/create', [\App\Http\Controllers\ItemInventorySettingController::class, 'create'])
        ->name('item-inventory-settings.create');

    Route::get('/master-data/item-inventory-settings/{item_inventory_setting}/edit', [\App\Http\Controllers\ItemInventorySettingController::class, 'edit'])
        ->name('item-inventory-settings.edit');

    Route::get('/master-data/item-inventory-settings/{item_inventory_setting}', [\App\Http\Controllers\ItemInventorySettingController::class, 'show'])
        ->name('item-inventory-settings.show');
});
