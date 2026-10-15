<?php

declare(strict_types=1);

use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\Jobs\DispatchCampaignRecipientJob;
use App\Modules\Communication\Domain\Enums\CampaignChannel;
use App\Modules\Communication\Domain\Enums\CampaignRecipientStatus;
use App\Modules\Communication\Domain\Enums\CampaignStatus;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\Campaign;
use App\Modules\Communication\Domain\Models\CampaignRecipient;
use App\Modules\Communication\Domain\Models\CampaignRun;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\Models\NotificationPreference;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Carbon;

function qaVerifyCampaignRecipient(User $customer): array
{
    $campaign = Campaign::factory()->running()->create(['channel' => CampaignChannel::Email]);
    $run = CampaignRun::factory()->create([
        'campaign_id' => $campaign->id,
        'completed_at' => null,
        'recipients_total' => 1,
        'recipients_sent' => 0,
        'recipients_failed' => 0,
    ]);
    $recipient = CampaignRecipient::factory()->create([
        'campaign_run_id' => $run->id,
        'user_id' => $customer->id,
        'status' => CampaignRecipientStatus::Queued,
    ]);

    return [$campaign, $run, $recipient];
}

it('permanently skips an overnight campaign recipient during local quiet hours', function (): void {
    $this->travelTo(Carbon::parse('2026-10-14 01:00:00', 'America/Los_Angeles')->utc());
    $customer = User::factory()->asCustomer()->create();
    [$campaign, $run, $recipient] = qaVerifyCampaignRecipient($customer);
    NotificationPreference::factory()->create([
        'user_id' => $customer->id,
        'channel' => NotificationChannel::Email,
        'event_category' => EventCategory::Marketing,
        'is_enabled' => true,
        'quiet_hours_start' => '22:00:00',
        'quiet_hours_end' => '07:00:00',
        'timezone' => 'America/Los_Angeles',
    ]);

    $dispatcher = Mockery::mock(DispatchNotificationAction::class);
    $dispatcher->shouldReceive('execute')->never();
    $job = (new DispatchCampaignRecipientJob($campaign->id, $run->id, $customer->id))->withFakeQueueInteractions();
    $job->handle($dispatcher);

    $job->assertNotReleased();
    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Skipped)
        ->and($run->fresh()->completed_at)->not->toBeNull()
        ->and($run->fresh()->recipients_total)->toBe(1)
        ->and($run->fresh()->recipients_sent)->toBe(0)
        ->and($run->fresh()->recipients_failed)->toBe(0)
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::Completed);

    $job->handle($dispatcher);
    $job->assertNotReleased();
    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Skipped)
        ->and(NotificationDispatch::query()->count())->toBe(0);
});

it('permanently skips a daytime campaign recipient during local quiet hours', function (): void {
    $this->travelTo(Carbon::parse('2026-10-14 10:00:00', 'Africa/Cairo')->utc());
    $customer = User::factory()->asCustomer()->create();
    [$campaign, $run, $recipient] = qaVerifyCampaignRecipient($customer);
    NotificationPreference::factory()->create([
        'user_id' => $customer->id,
        'channel' => NotificationChannel::Email,
        'event_category' => EventCategory::Marketing,
        'is_enabled' => true,
        'quiet_hours_start' => '09:00',
        'quiet_hours_end' => '17:00',
        'timezone' => 'Africa/Cairo',
    ]);

    $dispatcher = Mockery::mock(DispatchNotificationAction::class);
    $dispatcher->shouldReceive('execute')->never();
    $job = (new DispatchCampaignRecipientJob($campaign->id, $run->id, $customer->id))->withFakeQueueInteractions();
    $job->handle($dispatcher);

    $job->assertNotReleased();
    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Skipped)
        ->and($run->fresh()->completed_at)->not->toBeNull()
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::Completed);
});

it('dispatches at the exact quiet-hours end and when quiet hours are unset', function (): void {
    $this->travelTo(Carbon::parse('2026-10-14 07:00:00', 'Africa/Cairo')->utc());

    $customer = User::factory()->asCustomer()->create();
    [$campaign, $run] = qaVerifyCampaignRecipient($customer);
    NotificationPreference::factory()->create([
        'user_id' => $customer->id,
        'channel' => NotificationChannel::Email,
        'event_category' => EventCategory::Marketing,
        'is_enabled' => true,
        'quiet_hours_start' => '22:00',
        'quiet_hours_end' => '07:00',
        'timezone' => 'Africa/Cairo',
    ]);
    $dispatcher = Mockery::mock(DispatchNotificationAction::class);
    $dispatcher->shouldReceive('execute')->once()->andReturn(null);
    $job = (new DispatchCampaignRecipientJob($campaign->id, $run->id, $customer->id))->withFakeQueueInteractions();
    $job->handle($dispatcher);
    $job->assertNotReleased();

    $noHoursCustomer = User::factory()->asCustomer()->create();
    [$noHoursCampaign, $noHoursRun, $noHoursRecipient] = qaVerifyCampaignRecipient($noHoursCustomer);
    $noHoursDispatcher = Mockery::mock(DispatchNotificationAction::class);
    $noHoursDispatcher->shouldReceive('execute')->once()->andReturn(null);
    $noHoursJob = (new DispatchCampaignRecipientJob($noHoursCampaign->id, $noHoursRun->id, $noHoursCustomer->id))
        ->withFakeQueueInteractions();
    $noHoursJob->handle($noHoursDispatcher);
    $noHoursJob->assertNotReleased();

    expect($noHoursRecipient->fresh()->status)->toBe(CampaignRecipientStatus::Sent);
});

it('skips opt-outs instead of releasing them during quiet hours', function (): void {
    $this->travelTo(Carbon::parse('2026-10-14 01:00:00', 'Africa/Cairo')->utc());
    $customer = User::factory()->asCustomer()->create();
    [$campaign, $run, $recipient] = qaVerifyCampaignRecipient($customer);
    NotificationPreference::factory()->disabled()->create([
        'user_id' => $customer->id,
        'channel' => NotificationChannel::Email,
        'event_category' => EventCategory::Marketing,
        'quiet_hours_start' => '22:00',
        'quiet_hours_end' => '07:00',
        'timezone' => 'Africa/Cairo',
    ]);

    $dispatcher = Mockery::mock(DispatchNotificationAction::class);
    $dispatcher->shouldReceive('execute')->never();
    $job = (new DispatchCampaignRecipientJob($campaign->id, $run->id, $customer->id))->withFakeQueueInteractions();
    $job->handle($dispatcher);

    $job->assertNotReleased();
    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Skipped)
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::Completed);
});
