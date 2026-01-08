<?php

use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth:sanctum', 'role:finance|super_admin'])->prefix('payments')->name('payments.')->group(function () {
    // Get payment statistics
    Route::get('/stats', [PaymentController::class, 'stats'])->name('stats');

    // Outstanding purchase orders list
    Route::get('/outstanding', [PaymentController::class, 'index'])->name('outstanding');

    // Purchase order payment details
    Route::get('/purchase-orders/{purchaseOrder}', [PaymentController::class, 'show'])->name('purchase-order.show');
    Route::get('/purchase-orders/{purchaseOrder}/create', [PaymentController::class, 'create'])->name('purchase-order.create');

    // Payment CRUD
    Route::post('/', [PaymentController::class, 'store'])->name('store');
    Route::get('/{payment}/edit', [PaymentController::class, 'edit'])->name('edit');
    Route::put('/{payment}', [PaymentController::class, 'update'])->name('update');

    // Payment actions
    Route::post('/{payment}/confirm', [PaymentController::class, 'confirm'])->name('confirm');
    Route::post('/{payment}/cancel', [PaymentController::class, 'cancel'])->name('cancel');
});
