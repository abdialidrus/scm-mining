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
});
