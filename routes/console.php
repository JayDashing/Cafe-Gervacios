<?php

use App\Models\Booking;
use App\Models\QueueEntry;
use App\Models\SmsLog;
use App\Services\AutomationEngine;
use App\Services\TableService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('auth:clear-lockouts {ip? : IP address to clear; defaults to local development addresses}', function () {
    if (app()->environment('production')) {
        $this->error('auth:clear-lockouts is disabled in production.');

        return 1;
    }

    $argument = $this->argument('ip');
    $ips = $argument ? [$argument] : ['127.0.0.1', '::1'];

    foreach (array_unique($ips) as $ip) {
        // Development/testing helper only: Laravel hashes named throttle keys as md5("admin-login".$ip).
        foreach ([md5('admin-login'.$ip), 'admin-login:'.$ip, $ip] as $key) {
            RateLimiter::clear($key);
        }

        $this->line("Cleared admin login lockout for {$ip}.");
    }

    $this->info('Admin login lockouts cleared.');

    return 0;
})->purpose('Clear local/testing admin login rate limiter keys');

// Auto-release expired tables every 15 minutes
Schedule::call(function () {
    app(TableService::class)->releaseExpired();
})->everyFifteenMinutes()->name('release-expired-tables');

// Sync Facebook posts every 6 hours
Schedule::command('blog:sync-facebook')
    ->everySixHours()
    ->name('sync-facebook-posts')
    ->withoutOverlapping();

// RA 10173 — Hard delete queue PII after 24 hours
Schedule::call(function () {
    QueueEntry::where('created_at', '<', now()->subDay())->delete();
})->daily()->at('03:00')->name('purge-queue-entries');

// RA 10173 — Hard delete booking PII after 6 months
Schedule::call(function () {
    Booking::where('created_at', '<', now()->subMonths(6))->delete();
})->daily()->at('03:30')->name('purge-old-bookings');

// Purge admin logs older than 1 year
Schedule::call(function () {
    \App\Models\AdminLog::where('created_at', '<', now()->subYear())->delete();
})->weekly()->name('purge-old-admin-logs');

/*
|--------------------------------------------------------------------------
| Unified automation (queue holds, wait SMS, no-shows, reminders, etc.)
|--------------------------------------------------------------------------
*/

Schedule::call(fn() => AutomationEngine::run('queue_holds'))
    ->everyMinute()
    ->name('automation-queue-holds')
    ->withoutOverlapping();

Schedule::call(fn() => AutomationEngine::run('wait_estimates'))
    ->everyMinute()
    ->name('automation-wait-estimates')
    ->withoutOverlapping();

Schedule::call(fn() => AutomationEngine::run('no_shows'))
    ->everyFiveMinutes()
    ->name('automation-no-shows')
    ->withoutOverlapping();

Schedule::call(fn() => AutomationEngine::run('late_checkin'))
    ->everyFiveMinutes()
    ->name('automation-late-checkin')
    ->withoutOverlapping();

Schedule::call(fn() => AutomationEngine::run('reminders'))
    ->everyFifteenMinutes()
    ->name('automation-reminders')
    ->withoutOverlapping();

Schedule::call(fn() => AutomationEngine::run('reservation_table_release'))
    ->everyFifteenMinutes()
    ->name('automation-reservation-table-release')
    ->withoutOverlapping();

Schedule::command('reports:export')
    ->dailyAt('00:05')
    ->name('reports-daily-csv')
    ->withoutOverlapping();

Schedule::command('reports:export --weekly')
    ->weeklyOn(1, '8:00')
    ->name('reports-weekly-csv')
    ->withoutOverlapping();

Schedule::call(function () {
    SmsLog::where('created_at', '<', now()->subDays(30))->delete();
})->dailyAt('03:15')->name('purge-sms-logs');
