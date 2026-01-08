<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified', 'role:finance|super_admin'])->prefix('payments')->group(function () {
    // List of outstanding purchase orders
    Route::get('/', function () {
        return Inertia::render('Payments/Index');
    })->name('payments.index');

    // Purchase order detail with payment history
    Route::get('/purchase-orders/{purchaseOrderId}', function ($purchaseOrderId) {
        return Inertia::render('Payments/Show', [
            'purchaseOrderId' => (int) $purchaseOrderId,
        ]);
    })->name('payments.purchase-order.show');

    // Record payment form
    Route::get('/purchase-orders/{purchaseOrderId}/create', function ($purchaseOrderId) {
        return Inertia::render('Payments/PaymentForm', [
            'purchaseOrderId' => (int) $purchaseOrderId,
        ]);
    })->name('payments.purchase-order.create');
});
