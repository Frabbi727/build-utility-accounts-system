<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/**
 * Bills are raised on the first of the month. Generation is idempotent per
 * (flat, billing month), so a retry after a failed run bills nobody twice.
 */
Schedule::command('billing:generate')
    ->monthlyOn(1, '00:30')
    ->withoutOverlapping();

/**
 * Late fees are charged once per calendar month per bill, so a daily run is safe and
 * picks each bill up the day it becomes overdue.
 */
Schedule::command('billing:late-fees')
    ->dailyAt('01:00')
    ->withoutOverlapping();

/**
 * Automated push & in-app reminders for upcoming, due, and overdue bills.
 */
Schedule::command('notifications:send-bill-reminders')
    ->dailyAt('08:00')
    ->withoutOverlapping();

/**
 * Scan open maintenance tickets hourly for SLA breaches.
 */
Schedule::command('maintenance:check-sla')
    ->hourly()
    ->withoutOverlapping();

/**
 * Nightly complete database and data backup at 11:30 PM (Asia/Dhaka / local time).
 */
Schedule::command('backup:run')
    ->dailyAt(config('backup.scheduled_time', '23:30'))
    ->withoutOverlapping();
