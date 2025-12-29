<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/picking-orders', function () {
        return Inertia::render('picking-orders/Index');
    })->name('picking-orders.index');

    Route::get('/picking-orders/create', function () {
        return Inertia::render('picking-orders/Form', [
            'pickingOrderId' => null,
        ]);
    })->name('picking-orders.create');

    Route::get('/picking-orders/{pickingOrder}', function (\App\Models\PickingOrder $pickingOrder) {
        return Inertia::render('picking-orders/Show', [
            'pickingOrderId' => $pickingOrder->id,
        ]);
    })->name('picking-orders.show');
});
