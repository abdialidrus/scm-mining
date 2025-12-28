<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

require __DIR__ . '/settings.php';
require __DIR__ . '/approval_workflows.php';
require __DIR__ . '/purchase_requests.php';
require __DIR__ . '/purchase_orders.php';
require __DIR__ . '/master_data.php';
require __DIR__ . '/goods_receipts.php';
require __DIR__ . '/put_aways.php';
require __DIR__ . '/stock_reports.php';
