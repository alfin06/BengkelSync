<?php

use Illuminate\Support\Facades\Schedule;

// Production needs the standard Laravel cron entry: `* * * * * php artisan schedule:run`.

// Supplier-debt bell reminders, each morning at 07:00 shop time.
Schedule::command('purchases:notify-due')
    ->dailyAt('07:00')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping()
    ->onOneServer();
