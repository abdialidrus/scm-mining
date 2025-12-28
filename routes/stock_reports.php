<?php

use App\Http\Controllers\Api\StockReportController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Web routes for Inertia pages
Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/stock-reports', function () {
        return Inertia::render('stock-reports/Index');
    })->name('stock-reports.index');

    Route::get('/stock-reports/movements', function () {
        return Inertia::render('stock-reports/Movements');
    })->name('stock-reports.movements');
});

// API routes
Route::middleware('auth:sanctum')->prefix('api/stock-reports')->group(function () {
    Route::get('/by-location', [StockReportController::class, 'stockByLocation']);
    Route::get('/by-item', [StockReportController::class, 'stockSummaryByItem']);
    Route::get('/movements', [StockReportController::class, 'movements']);
    Route::get('/items/{item}/locations', [StockReportController::class, 'itemLocationBreakdown']);
});
