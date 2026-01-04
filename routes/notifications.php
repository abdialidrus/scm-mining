<?php

use App\Http\Controllers\NotificationController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware(['auth', 'verified'])->group(function () {
    // Notification Center
    Route::get('/notifications', function () {
        return Inertia::render('notifications/NotificationCenter');
    })->name('notifications.index');

    // Notification Preferences
    Route::get('/notifications/preferences', function () {
        return Inertia::render('notifications/NotificationPreferences');
    })->name('notifications.preferences');
});
