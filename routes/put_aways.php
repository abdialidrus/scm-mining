<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/put-aways', function () {
        return Inertia::render('put-aways/Index');
    })->name('put-aways.index');

    Route::get('/put-aways/create', function () {
        return Inertia::render('put-aways/Form', [
            'putAwayId' => null,
        ]);
    })->name('put-aways.create');

    Route::get('/put-aways/{putAway}', function (\App\Models\PutAway $putAway) {
        return Inertia::render('put-aways/Show', [
            'putAwayId' => $putAway->id,
        ]);
    })->name('put-aways.show');
});
