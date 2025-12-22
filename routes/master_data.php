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

    Route::get('/master-data/users', function () {
        return Inertia::render('master-data/users/Index');
    })->name('master-data.users.index');

    Route::get('/master-data/users/create', function () {
        return Inertia::render('master-data/users/Form', [
            'userId' => null,
        ]);
    })->name('master-data.users.create');

    Route::get('/master-data/users/{user}/edit', function (\App\Models\User $user) {
        return Inertia::render('master-data/users/Form', [
            'userId' => $user->id,
        ]);
    })->name('master-data.users.edit');
});
