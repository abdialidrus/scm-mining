<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/master-data/items', function () {
        return Inertia::render('master-data/items/Index');
    })->name('master-data.items.index');

    Route::get('/master-data/uoms', function () {
        return Inertia::render('master-data/uoms/Index');
    })->name('master-data.uoms.index');

    Route::get('/master-data/departments', function () {
        return Inertia::render('master-data/departments/Index');
    })->name('master-data.departments.index');

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
});
