<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/purchase-orders', function () {
        return Inertia::render('purchase-orders/Index');
    })->name('purchase-orders.index');

    Route::get('/purchase-orders/create', function () {
        return Inertia::render('purchase-orders/CreateFromPr', []);
    })->name('purchase-orders.create');

    Route::get('/purchase-orders/{purchaseOrderId}', function (int $purchaseOrderId) {
        return Inertia::render('purchase-orders/Show', [
            'purchaseOrderId' => $purchaseOrderId,
        ]);
    })->name('purchase-orders.show');
});
