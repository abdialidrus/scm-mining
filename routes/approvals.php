<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/my-approvals', function () {
        return Inertia::render('approvals/MyApprovals');
    })->name('approvals.my-approvals');
});
