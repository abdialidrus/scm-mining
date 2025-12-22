<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/purchase-requests', function () {
        return Inertia::render('purchase-requests/Index');
    })->name('purchase-requests.index');

    Route::get('/purchase-requests/create', function () {
        return Inertia::render('purchase-requests/Form', [
            'purchaseRequestId' => null,
        ]);
    })->name('purchase-requests.create');

    Route::get('/purchase-requests/{purchaseRequestId}', function (int $purchaseRequestId) {
        return Inertia::render('purchase-requests/Show', [
            'purchaseRequestId' => $purchaseRequestId,
        ]);
    })->name('purchase-requests.show');

    Route::get('/purchase-requests/{purchaseRequestId}/edit', function (int $purchaseRequestId) {
        return Inertia::render('purchase-requests/Form', [
            'purchaseRequestId' => $purchaseRequestId,
        ]);
    })->name('purchase-requests.edit');
});
