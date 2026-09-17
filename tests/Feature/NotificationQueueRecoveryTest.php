<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Jobs\DispatchNotificationJob;
use App\Modules\Communication\Domain\Enums\CampaignStatus;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Models\Campaign;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

it('dispatches due campaigns once and leaves future campaigns scheduled', function () {
    $due = Campaign::factory()->scheduled()->create([
        'scheduled_at' => now()->subMinute(),
        'segment_filters' => ['booked_within_days' => 365],
    ]);
    $future = Campaign::factory()->scheduled()->create([
        'scheduled_at' => now()->addHour(),
        'segment_filters' => ['booked_within_days' => 365],
    ]);

    $this->artisan('communication:dispatch-due-campaigns')->assertSuccessful();

    expect($due->refresh()->status)->toBe(CampaignStatus::Completed)
        ->and($due->runs()->count())->toBe(1)
        ->and($future->refresh()->status)->toBe(CampaignStatus::Scheduled)
        ->and($future->runs()->count())->toBe(0);
});

it('recovers stale queued dispatches through the existing retry path', function () {
    Queue::fake();

    $dispatch = NotificationDispatch::factory()->create([
        'status' => DispatchStatus::Queued,
        'attempt_count' => 0,
        'created_at' => now()->subMinutes(20),
        'next_retry_at' => null,
    ]);

    $this->artisan('communication:retry-failed-dispatches')->assertSuccessful();

    $dispatch->refresh();

    expect($dispatch->status)->toBe(DispatchStatus::Queued)
        ->and($dispatch->next_retry_at?->isFuture())->toBeTrue()
        ->and(DB::table('audit_logs')->where('auditable_id', $dispatch->id)
            ->where('action', 'notification.stale_dispatch_requeued')->exists())->toBeTrue();

    Queue::assertPushed(DispatchNotificationJob::class, fn (DispatchNotificationJob $job) => $job->dispatchId === $dispatch->id);
});

it('claims a queued dispatch only once when duplicate jobs run', function () {
    $dispatch = NotificationDispatch::factory()->create([
        'status' => DispatchStatus::Queued,
        'attempt_count' => 0,
    ]);
    $job = new DispatchNotificationJob($dispatch->id);

    $job->handle(app());
    $job->handle(app());

    $dispatch->refresh();

    expect($dispatch->status)->toBe(DispatchStatus::Sent)
        ->and($dispatch->attempt_count)->toBe(1);
});
