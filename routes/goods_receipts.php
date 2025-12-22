<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/goods-receipts', function () {
        return Inertia::render('goods-receipts/Index');
    })->name('goods-receipts.index');

    Route::get('/goods-receipts/create', function () {
        return Inertia::render('goods-receipts/Form', [
            'goodsReceiptId' => null,
        ]);
    })->name('goods-receipts.create');

    Route::get('/goods-receipts/{goodsReceipt}', function (\App\Models\GoodsReceipt $goodsReceipt) {
        return Inertia::render('goods-receipts/Show', [
            'goodsReceiptId' => $goodsReceipt->id,
        ]);
    })->name('goods-receipts.show');
});
