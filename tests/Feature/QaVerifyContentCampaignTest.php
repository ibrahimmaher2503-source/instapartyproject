<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Communication\Application\Actions\CampaignAlreadyDispatchedException;
use App\Modules\Communication\Application\Actions\CancelCampaignAction;
use App\Modules\Communication\Application\Actions\DispatchCampaignAction;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\Jobs\DispatchCampaignRecipientJob;
use App\Modules\Communication\Domain\Enums\CampaignChannel;
use App\Modules\Communication\Domain\Enums\CampaignRecipientStatus;
use App\Modules\Communication\Domain\Enums\CampaignStatus;
use App\Modules\Communication\Domain\Enums\CampaignTargetLocale;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\Campaign;
use App\Modules\Communication\Domain\Models\CampaignRecipient;
use App\Modules\Communication\Domain\Models\CampaignRun;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\Models\NotificationPreference;
use App\Modules\Communication\Filament\Resources\CampaignResource\Pages\CreateCampaign;
use App\Modules\Identity\Domain\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any_campaign', 'view_campaign', 'create_campaign'] as $ability) {
        $admin->givePermissionTo(Permission::findOrCreate($ability, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

it('creates a bilingual campaign with its requested future dispatch time', function (): void {
    $scheduledAt = now()->addDay()->startOfMinute();
    $inputTime = $scheduledAt->copy()->timezone(auth()->user()->timezone)->format('Y-m-d H:i:s');

    Livewire::test(CreateCampaign::class)
        ->assertFormFieldExists('scheduled_at')
        ->fillForm([
            'name' => 'QA schedule '.str()->ulid(),
            'channel' => 'email',
            'target_locale' => 'both',
            'scheduled_at' => $inputTime,
            'segment_filters' => ['preferred_locale' => 'en'],
            'subject' => ['en' => 'QA subject', 'ar' => 'عنوان اختبار'],
            'body' => ['en' => 'QA body', 'ar' => 'محتوى الاختبار'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $campaign = Campaign::query()->sole();

    expect($campaign->status)->toBe(CampaignStatus::Scheduled)
        ->and($campaign->scheduled_at?->toIso8601String())->toBe($scheduledAt->toIso8601String())
        ->and($campaign->getTranslation('body', 'en', false))->toBe('QA body')
        ->and($campaign->getTranslation('body', 'ar', false))->toBe('محتوى الاختبار');
});

it('rejects an empty audience filter and a past schedule without creating a campaign', function (): void {
    $base = [
        'name' => 'QA invalid '.str()->ulid(),
        'channel' => 'email',
        'target_locale' => 'both',
        'subject' => ['en' => 'QA subject', 'ar' => 'عنوان اختبار'],
        'body' => ['en' => 'QA body', 'ar' => 'محتوى الاختبار'],
    ];

    Livewire::test(CreateCampaign::class)
        ->fillForm($base)
        ->call('create')
        ->assertHasFormErrors(['segment_filters']);

    $past = now()->subDay()->timezone(auth()->user()->timezone)->format('Y-m-d H:i:s');
    Livewire::test(CreateCampaign::class)
        ->fillForm($base + [
            'scheduled_at' => $past,
            'segment_filters' => ['preferred_locale' => 'en'],
        ])
        ->call('create')
        ->assertHasFormErrors(['scheduled_at']);

    expect(Campaign::query()->count())->toBe(0);
});

it('uses the due command to dispatch only the matching booking audience once', function (): void {
    Queue::fake();

    $englishCustomer = User::factory()->asCustomer()->create(['preferred_locale' => 'en']);
    $arabicCustomer = User::factory()->asCustomer()->create(['preferred_locale' => 'ar']);

    foreach ([$englishCustomer, $arabicCustomer] as $customer) {
        $booking = Booking::factory()->create(['customer_id' => $customer->id]);
        $bookingVendor = BookingVendor::factory()->create(['booking_id' => $booking->id]);
        BookingItem::factory()->sale()->create(['booking_vendor_id' => $bookingVendor->id]);
    }

    $campaign = Campaign::factory()->scheduled()->create([
        'channel' => CampaignChannel::Email,
        'target_locale' => CampaignTargetLocale::Both,
        'segment_filters' => ['preferred_locale' => 'en'],
        'scheduled_at' => now()->subMinute(),
    ]);

    $this->artisan('communication:dispatch-due-campaigns')->assertSuccessful();

    $run = CampaignRun::query()->where('campaign_id', $campaign->id)->sole();
    $recipients = CampaignRecipient::query()->where('campaign_run_id', $run->id)->get();

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Running)
        ->and($run->recipients_total)->toBe(1)
        ->and($recipients->pluck('user_id')->all())->toBe([$englishCustomer->id])
        ->and(NotificationDispatch::query()->count())->toBe(0);

    Queue::assertPushed(DispatchCampaignRecipientJob::class, fn (DispatchCampaignRecipientJob $job): bool => $job->campaignId === $campaign->id
        && $job->campaignRunId === $run->id
        && $job->userId === $englishCustomer->id);

    expect(fn () => app(DispatchCampaignAction::class)->execute($campaign->fresh()))
        ->toThrow(CampaignAlreadyDispatchedException::class);

    expect($campaign->runs()->count())->toBe(1)
        ->and(CampaignRecipient::query()->where('campaign_run_id', $run->id)->count())->toBe(1);

    Queue::assertPushed(DispatchCampaignRecipientJob::class, 1);
});

it('completes a valid segment with no matching audience without queueing recipients', function (): void {
    Queue::fake();

    $campaign = Campaign::factory()->scheduled()->create([
        'scheduled_at' => now()->subMinute(),
        'segment_filters' => ['preferred_locale' => 'fr'],
    ]);

    $this->artisan('communication:dispatch-due-campaigns')->assertSuccessful();

    expect($campaign->fresh()->status)->toBe(CampaignStatus::Completed)
        ->and($campaign->runs()->sole()->recipients_total)->toBe(0)
        ->and(CampaignRecipient::query()->count())->toBe(0)
        ->and(NotificationDispatch::query()->count())->toBe(0);

    Queue::assertNothingPushed();
});

it('cancels scheduled campaigns and skips opted-out marketing recipients', function (): void {
    $scheduled = Campaign::factory()->scheduled()->create(['scheduled_at' => now()->addDay()]);
    app(CancelCampaignAction::class)->execute($scheduled);
    expect($scheduled->fresh()->status)->toBe(CampaignStatus::Cancelled);

    $customer = User::factory()->asCustomer()->create();
    $campaign = Campaign::factory()->running()->create([
        'channel' => CampaignChannel::Email,
    ]);
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
    NotificationPreference::factory()->disabled()->create([
        'user_id' => $customer->id,
        'channel' => NotificationChannel::Email,
        'event_category' => EventCategory::Marketing,
    ]);
    $dispatcher = Mockery::mock(DispatchNotificationAction::class);
    $dispatcher->shouldReceive('execute')->never();

    (new DispatchCampaignRecipientJob($campaign->id, $run->id, $customer->id))->handle($dispatcher);

    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Skipped)
        ->and($campaign->fresh()->status)->toBe(CampaignStatus::Completed)
        ->and(NotificationDispatch::query()->count())->toBe(0);
});
