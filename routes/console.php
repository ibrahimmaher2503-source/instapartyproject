<?php

use App\Modules\Booking\Application\Actions\ExpireStaleBookingModificationsAction;
use App\Modules\Payments\Application\Actions\ExpirePendingPaymentsAction;
use App\Modules\Payments\Infrastructure\Repositories\EloquentIdempotencyKeyRepository;
use App\Modules\Shared\Infrastructure\Jobs\OrphanMediaCleanupJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('booking:release-expired-reservations')->everyMinute()->withoutOverlapping();
Schedule::command('booking:expire-vendor-proposals')->everyMinute()->withoutOverlapping();
Schedule::command('booking:expire-stale-vendor-reviews')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping();
Schedule::call(fn () => app(ExpireStaleBookingModificationsAction::class)->execute())
    ->name('booking:expire-stale-modifications')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::call(fn () => app(ExpirePendingPaymentsAction::class)->execute())
    ->name('payments:expire-pending-holds')
    ->everyFifteenMinutes()
    ->onOneServer()
    ->withoutOverlapping();

Schedule::call(fn () => app(EloquentIdempotencyKeyRepository::class)->purgeExpired())
    ->name('payments:purge-expired-idempotency-keys')
    ->daily()
    ->onOneServer()
    ->withoutOverlapping();

// Settlement reconciliation — hourly recent-touch and daily full scan
Schedule::command('reconcile:run --scope=recent_touch --window=60min')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('reconcile:run --scope=all')
    ->dailyAt('04:00')
    ->timezone('Africa/Cairo')
    ->withoutOverlapping()
    ->onOneServer();

Schedule::command('ledger:snapshot --all')
    ->dailyAt('04:30')
    ->timezone('Africa/Cairo')
    ->onOneServer();

// Media foundation — daily orphan cleanup (FR-EXT-MED-004, ADR-0047 §6).
Schedule::job(new OrphanMediaCleanupJob)
    ->name('media:cleanup-orphans')
    ->dailyAt('03:00')
    ->onOneServer()
    ->withoutOverlapping();
