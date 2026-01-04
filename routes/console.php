<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Scheduled Tasks
Schedule::command('approvals:send-reminders')
    ->daily()
    ->at('09:00')
    ->timezone('Asia/Jakarta')
    ->description('Send daily approval reminders to users with pending approvals');

Schedule::command('inventory:send-low-stock-alerts')
    ->daily()
    ->at('08:00')
    ->timezone('Asia/Jakarta')
    ->description('Send daily low stock alerts to inventory managers');
