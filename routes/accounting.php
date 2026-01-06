<?php

use App\Http\Controllers\Accounting\InvoiceMatchingController;
use App\Http\Controllers\Accounting\InvoicePaymentController;
use App\Http\Controllers\Accounting\SupplierInvoiceController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Accounting Routes - Invoice Management
|--------------------------------------------------------------------------
*/

Route::middleware(['auth:sanctum', 'verified'])->prefix('accounting')->name('accounting.')->group(function () {

    // Supplier Invoices
    Route::prefix('invoices')->name('invoices.')->group(function () {
        // Main CRUD
        Route::get('/', [SupplierInvoiceController::class, 'index'])->name('index');
        Route::get('/create', [SupplierInvoiceController::class, 'create'])->name('create');
        Route::post('/', [SupplierInvoiceController::class, 'store'])->name('store');
        Route::get('/{supplierInvoice}', [SupplierInvoiceController::class, 'show'])->name('show');
        Route::get('/{supplierInvoice}/edit', [SupplierInvoiceController::class, 'edit'])->name('edit');
        Route::put('/{supplierInvoice}', [SupplierInvoiceController::class, 'update'])->name('update');
        Route::delete('/{supplierInvoice}', [SupplierInvoiceController::class, 'destroy'])->name('destroy');

        // Status Actions
        Route::post('/{supplierInvoice}/submit', [SupplierInvoiceController::class, 'submit'])->name('submit');
        Route::post('/{supplierInvoice}/cancel', [SupplierInvoiceController::class, 'cancel'])->name('cancel');

        // File Downloads
        Route::get('/{supplierInvoice}/download/invoice', [SupplierInvoiceController::class, 'downloadInvoiceFile'])->name('download.invoice');
        Route::get('/{supplierInvoice}/download/tax-invoice', [SupplierInvoiceController::class, 'downloadTaxInvoiceFile'])->name('download.tax-invoice');

        // Helper Endpoints
        Route::get('/po/{purchaseOrder}/details', [SupplierInvoiceController::class, 'getPurchaseOrderDetails'])->name('po.details');

        // Matching Routes
        Route::prefix('{supplierInvoice}/matching')->name('matching.')->group(function () {
            Route::post('/', [InvoiceMatchingController::class, 'match'])->name('run');
            Route::get('/', [InvoiceMatchingController::class, 'show'])->name('show');
            Route::post('/approve', [InvoiceMatchingController::class, 'approve'])->name('approve');
            Route::post('/reject', [InvoiceMatchingController::class, 'reject'])->name('reject');
        });

        // Payment Routes
        Route::prefix('{supplierInvoice}/payments')->name('payments.')->group(function () {
            Route::get('/', [InvoicePaymentController::class, 'index'])->name('index');
            Route::post('/', [InvoicePaymentController::class, 'store'])->name('store');
            Route::get('/{payment}/download', [InvoicePaymentController::class, 'downloadProof'])->name('download');
        });
    });

    // Tolerance Configuration
    Route::prefix('tolerance-config')->name('tolerance.')->group(function () {
        Route::get('/', [InvoiceMatchingController::class, 'getToleranceConfig'])->name('show');
        Route::post('/', [InvoiceMatchingController::class, 'updateToleranceConfig'])->name('update');
    });

    // Payment Reports & Summary
    Route::prefix('payment-reports')->name('payments.')->group(function () {
        Route::get('/summary', [InvoicePaymentController::class, 'getSummary'])->name('summary');
        Route::get('/export', [InvoicePaymentController::class, 'exportReport'])->name('export');
    });
});
