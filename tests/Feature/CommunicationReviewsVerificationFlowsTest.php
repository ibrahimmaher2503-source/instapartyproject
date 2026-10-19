<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Events\BookingSubmittedToVendor;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Resources\BookingResource;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Communication\Application\Actions\CancelCampaignAction;
use App\Modules\Communication\Application\Actions\CreateCampaignAction;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\BuildCampaignDTO;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Application\Jobs\DispatchCampaignRecipientJob;
use App\Modules\Communication\Application\Jobs\DispatchNotificationJob;
use App\Modules\Communication\Application\Listeners\OnBookingSubmitted;
use App\Modules\Communication\Application\Services\InvalidSegmentFilterException;
use App\Modules\Communication\Application\Services\TemplateResolver;
use App\Modules\Communication\Domain\Enums\CampaignChannel;
use App\Modules\Communication\Domain\Enums\CampaignRecipientStatus;
use App\Modules\Communication\Domain\Enums\CampaignStatus;
use App\Modules\Communication\Domain\Enums\CampaignTargetLocale;
use App\Modules\Communication\Domain\Enums\DispatchStatus;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\AdminInboxItem;
use App\Modules\Communication\Domain\Models\Campaign;
use App\Modules\Communication\Domain\Models\CampaignRecipient;
use App\Modules\Communication\Domain\Models\CampaignRun;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\Models\NotificationPreference;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use App\Modules\Communication\Filament\Resources\AdminInboxResource\Pages\ListAdminInboxItems;
use App\Modules\Communication\Filament\Resources\CampaignResource\Pages\CreateCampaign;
use App\Modules\Communication\Filament\Resources\NotificationTemplateResource;
use App\Modules\Communication\Filament\Resources\NotificationTemplateResource\Pages\EditNotificationTemplate;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Reviews\Application\Actions\ModerateReviewResponseAction;
use App\Modules\Reviews\Application\Actions\RespondToReviewAction;
use App\Modules\Reviews\Domain\Enums\ModerationStatus;
use App\Modules\Reviews\Domain\Enums\ReviewType;
use App\Modules\Reviews\Domain\Models\ReviewModerationLog;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use App\Modules\Reviews\Domain\Policies\ServiceReviewPolicy;
use App\Modules\Reviews\Filament\Resources\ServiceReviewResource\Pages\ListServiceReviews;
use App\Modules\Reviews\Filament\Resources\VendorReviewResource\Pages\ListVendorReviews;
use Filament\Facades\Filament;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('submits eligible customer service and vendor reviews and moderates them through admin', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    $service = Service::factory()->published()->create(['vendor_profile_id' => $vendor->id]);
    $booking = Booking::factory()->completed()->create(['customer_id' => $customer->id]);
    $bookingVendor = BookingVendor::factory()->completed()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
    ]);
    $bookingItem = BookingItem::factory()->rental()->create([
        'booking_vendor_id' => $bookingVendor->id,
        'service_id' => $service->id,
        'item_status' => 'completed',
    ]);

    $otherCustomer = User::factory()->asCustomer()->create();
    $this->actingAs($otherCustomer, 'sanctum')
        ->postJson('/api/v1/customer/booking-items/'.$bookingItem->public_id.'/review', [
            'rating' => 5,
            'body' => 'Not my booking.',
        ])->assertUnprocessable()->assertJsonPath('errors.0.code', 'booking_item_not_completed');
    expect(ServiceReview::query()->count())->toBe(0);

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/customer/booking-items/'.$bookingItem->public_id.'/review', [
            'rating' => 5,
            'body' => 'Excellent service.',
        ])->assertCreated()->assertJsonPath('data.moderation_status', 'pending');
    $this->postJson('/api/v1/customer/booking-vendors/'.$bookingVendor->public_id.'/review', [
        'rating' => 4,
        'body' => 'Professional vendor.',
    ])->assertCreated()->assertJsonPath('data.moderation_status', 'pending');

    $serviceReview = ServiceReview::query()->sole();
    $vendorReview = VendorReview::query()->sole();
    $secondBooking = Booking::factory()->completed()->create(['customer_id' => $customer->id]);
    $secondBookingVendor = BookingVendor::factory()->completed()->create([
        'booking_id' => $secondBooking->id,
        'vendor_profile_id' => $vendor->id,
    ]);
    BookingItem::factory()->rental()->create([
        'booking_vendor_id' => $secondBookingVendor->id,
        'service_id' => $service->id,
        'item_status' => 'completed',
    ]);
    $this->postJson('/api/v1/customer/booking-vendors/'.$secondBookingVendor->public_id.'/review', [
        'rating' => 5,
        'body' => 'Another excellent booking.',
    ])->assertCreated();
    $approvedVendorReview = VendorReview::query()->where('booking_vendor_id', $secondBookingVendor->id)->sole();
    expect($serviceReview->booking_item_id)->toBe($bookingItem->id)
        ->and($serviceReview->service_id)->toBe($service->id)
        ->and($serviceReview->user_id)->toBe($customer->id)
        ->and($vendorReview->booking_vendor_id)->toBe($bookingVendor->id)
        ->and($vendorReview->vendor_profile_id)->toBe($vendor->id)
        ->and($vendorReview->user_id)->toBe($customer->id);

    $this->postJson('/api/v1/customer/booking-items/'.$bookingItem->public_id.'/review', [
        'rating' => 5,
    ])->assertConflict()->assertJsonPath('errors.0.code', 'review_already_exists');

    $admin = User::factory()->asAdmin()->create();
    foreach (['view_any_service::review', 'update_service::review', 'view_any_vendor::review', 'update_vendor::review'] as $ability) {
        $admin->givePermissionTo(Permission::findOrCreate($ability, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    expect($admin->can('view_any_service::review'))->toBeTrue()
        ->and(Gate::getPolicyFor(ServiceReview::class))
        ->toBeInstanceOf(ServiceReviewPolicy::class);
    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(ListServiceReviews::class)
        ->assertSuccessful()
        ->assertSee($serviceReview->public_id)
        ->callTableAction('approve', $serviceReview);
    Livewire::test(ListVendorReviews::class)
        ->assertSuccessful()
        ->assertSee($vendorReview->public_id)
        ->callTableAction('reject', $vendorReview, data: [
            'reason_en' => 'Needs moderation.',
            'reason_ar' => 'يحتاج إلى مراجعة.',
        ])
        ->callTableAction('approve', $approvedVendorReview);

    expect($serviceReview->fresh()->moderation_status)->toBe(ModerationStatus::Approved)
        ->and($vendorReview->fresh()->moderation_status)->toBe(ModerationStatus::Rejected)
        ->and($approvedVendorReview->fresh()->moderation_status)->toBe(ModerationStatus::Approved)
        ->and(ReviewModerationLog::query()->whereIn('review_id', [$serviceReview->id, $vendorReview->id, $approvedVendorReview->id])->count())->toBe(3);

    $serviceResponse = app(RespondToReviewAction::class)->execute(
        $serviceReview->id,
        ReviewType::Service,
        $vendor,
        'We are glad you enjoyed it.',
    );
    $vendorResponse = app(RespondToReviewAction::class)->execute(
        $approvedVendorReview->id,
        ReviewType::Vendor,
        $vendor,
        'Thank you for your feedback.',
    );
    app(ModerateReviewResponseAction::class)->execute($serviceResponse, ModerationStatus::Approved);
    app(ModerateReviewResponseAction::class)->execute($vendorResponse, ModerationStatus::Approved);

    $publicReviews = $this->getJson('/api/v1/public/services/'.$service->public_id.'/reviews')->assertOk();
    expect(collect($publicReviews->json('data'))->pluck('body')->all())->toBe(['Excellent service.'])
        ->and($publicReviews->json('data.0.vendor_response'))->toBe('We are glad you enjoyed it.');

    $vendorPublicReviews = $this->getJson('/api/v1/public/vendors/'.$vendor->public_id.'/reviews')->assertOk();
    expect($vendorPublicReviews->json('data'))->toHaveCount(1)
        ->and($vendorPublicReviews->json('data.0.vendor_response'))->toBe('Thank you for your feedback.');
});

it('creates, schedules, safely dispatches, cancels, and idempotently tracks a campaign', function (): void {
    Queue::fake();
    $action = app(CreateCampaignAction::class);
    expect(fn () => $action->execute(new BuildCampaignDTO(
        name: 'Invalid empty audience',
        channel: CampaignChannel::Email,
        targetLocale: CampaignTargetLocale::Both,
        segmentFilters: [],
        body: ['en' => 'English', 'ar' => 'العربية'],
        subject: [],
        createdBy: 1,
    )))->toThrow(InvalidSegmentFilterException::class);
    expect(fn () => $action->execute(new BuildCampaignDTO(
        name: 'Invalid past schedule',
        channel: CampaignChannel::Email,
        targetLocale: CampaignTargetLocale::Both,
        segmentFilters: ['preferred_locale' => 'en'],
        body: ['en' => 'English', 'ar' => 'العربية'],
        subject: [],
        createdBy: 1,
        scheduledAt: now()->subMinute()->toIso8601String(),
    )))->toThrow(InvalidArgumentException::class);
    expect(Campaign::query()->count())->toBe(0);

    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any_campaign', 'view_campaign', 'create_campaign'] as $ability) {
        $admin->givePermissionTo(Permission::findOrCreate($ability, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $scheduledAt = now()->addDay()->startOfMinute();
    Livewire::test(CreateCampaign::class)
        ->assertFormFieldExists('scheduled_at')
        ->fillForm([
            'name' => 'QA campaign '.Str::ulid(),
            'channel' => CampaignChannel::Email->value,
            'target_locale' => CampaignTargetLocale::Both->value,
            'scheduled_at' => $scheduledAt->copy()->timezone($admin->timezone)->format('Y-m-d H:i:s'),
            'segment_filters' => ['preferred_locale' => 'en'],
            'subject' => ['en' => 'QA subject', 'ar' => 'عنوان اختبار'],
            'body' => ['en' => 'QA body', 'ar' => 'محتوى الاختبار'],
        ])->call('create')->assertHasNoFormErrors();

    $campaign = Campaign::query()->sole();
    expect($campaign->status)->toBe(CampaignStatus::Scheduled)
        ->and($campaign->scheduled_at?->toIso8601String())->toBe($scheduledAt->toIso8601String())
        ->and($campaign->getTranslation('body', 'ar', false))->toBe('محتوى الاختبار');

    $englishCustomer = User::factory()->asCustomer()->create(['preferred_locale' => 'en']);
    $arabicCustomer = User::factory()->asCustomer()->create(['preferred_locale' => 'ar']);
    foreach ([$englishCustomer, $arabicCustomer] as $customer) {
        $booking = Booking::factory()->create(['customer_id' => $customer->id]);
        $bookingVendor = BookingVendor::factory()->create(['booking_id' => $booking->id]);
        BookingItem::factory()->sale()->create(['booking_vendor_id' => $bookingVendor->id]);
    }
    $campaign->update(['scheduled_at' => now()->subMinute()]);
    $this->artisan('communication:dispatch-due-campaigns')->assertSuccessful();
    $run = CampaignRun::query()->where('campaign_id', $campaign->id)->sole();
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Running)
        ->and($run->recipients_total)->toBe(1)
        ->and(CampaignRecipient::query()->where('campaign_run_id', $run->id)->count())->toBe(1)
        ->and(NotificationDispatch::query()->count())->toBe(0);
    Queue::assertPushed(DispatchCampaignRecipientJob::class, 1);

    $recipient = CampaignRecipient::query()->where('campaign_run_id', $run->id)->sole();
    $dispatcher = Mockery::mock(DispatchNotificationAction::class);
    $dispatcher->shouldReceive('execute')->once()->andReturn(null);
    $job = new DispatchCampaignRecipientJob($campaign->id, $run->id, $recipient->user_id);
    $job->handle($dispatcher);
    $job->handle($dispatcher);
    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Sent)
        ->and($run->fresh()->recipients_sent)->toBe(1)
        ->and(NotificationDispatch::query()->count())->toBe(0);

    $campaign->refresh()->update(['status' => CampaignStatus::Scheduled, 'scheduled_at' => now()->addDay()]);
    app(CancelCampaignAction::class)->execute($campaign);
    expect($campaign->fresh()->status)->toBe(CampaignStatus::Cancelled);
});

it('saves an edited bilingual template and renders it into an isolated queued dispatch', function (): void {
    Queue::fake();
    $admin = User::factory()->superAdmin()->create();
    foreach (['view_any_notification::template', 'view_notification::template', 'update_notification::template'] as $ability) {
        $admin->givePermissionTo(Permission::findOrCreate($ability, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $template = NotificationTemplate::factory()->create([
        'event_key' => 'booking.content_qa',
        'channel' => NotificationChannel::InApp,
        'audience' => NotificationAudience::Customer,
        'subject' => ['en' => 'Old subject', 'ar' => 'عنوان قديم'],
        'body' => ['en' => 'Old body', 'ar' => 'محتوى قديم'],
        'variables' => ['recipient_name' => 'Recipient name'],
    ]);
    $this->actingAs($admin);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $edit = Livewire::test(EditNotificationTemplate::class, ['record' => $template->getRouteKey()])
        ->fillForm([
            'subject' => 'Booking {{recipient_name}}',
            'body' => 'Booking {{recipient_name}} is confirmed.',
            'variables' => ['recipient_name' => 'Customer name'],
        ]);
    $edit->set('activeLocale', 'ar')->fillForm([
        'subject' => 'تحديث الحجز لـ {{recipient_name}}',
        'body' => 'تم تأكيد حجز {{recipient_name}}.',
        'variables' => ['recipient_name' => 'اسم العميل'],
    ])->call('save');
    expect($edit->errors()->toArray())->toBe([]);

    $template->refresh();
    expect($template->getTranslations('subject'))->toBe([
        'en' => 'Booking {{recipient_name}}',
        'ar' => 'تحديث الحجز لـ {{recipient_name}}',
    ])->and($template->getTranslations('body'))->toBe([
        'en' => 'Booking {{recipient_name}} is confirmed.',
        'ar' => 'تم تأكيد حجز {{recipient_name}}.',
    ]);

    $customer = User::factory()->create(['preferred_locale' => 'ar']);
    $dispatch = app(DispatchNotificationAction::class)->execute(new DispatchNotificationDTO(
        eventKey: 'booking.content_qa',
        channel: NotificationChannel::InApp,
        audience: NotificationAudience::Customer,
        eventCategory: EventCategory::Booking,
        userId: $customer->id,
        context: ['recipient_name' => '<QA name>'],
    ));
    expect($dispatch)->toBeInstanceOf(NotificationDispatch::class)
        ->and($dispatch->notification_template_id)->toBe($template->id)
        ->and($dispatch->user_id)->toBe($customer->id)
        ->and($dispatch->status)->toBe(DispatchStatus::Queued)
        ->and($dispatch->context['body'])->toBe('تم تأكيد حجز <QA name>.')
        ->and(app(TemplateResolver::class)->resolve('booking.content_qa', NotificationChannel::InApp, NotificationAudience::Customer, 'en')
            ->render(['recipient_name' => 'English QA'])['body'])->toBe('Booking English QA is confirmed.');
    Queue::assertPushed(DispatchNotificationJob::class, fn (DispatchNotificationJob $job): bool => $job->dispatchId === $dispatch->id);

    expect(fn () => NotificationTemplateResource::validateFormData([
        'subject' => ['en' => 'Booking {{booking_id}}', 'ar' => 'الحجز {{booking_id}}'],
        'body' => ['en' => 'Booking {{booking_id}}', 'ar' => 'الحجز {{booking_id}}'],
        'variables' => [],
    ]))->toThrow(ValidationException::class);
});

it('skips opted-in marketing recipients during their local quiet hours', function (): void {
    $this->travelTo(Carbon::parse('2026-10-14 01:00:00', 'Africa/Cairo')->utc());
    $customer = User::factory()->asCustomer()->create();
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
    NotificationPreference::factory()->create([
        'user_id' => $customer->id,
        'channel' => NotificationChannel::Email,
        'event_category' => EventCategory::Marketing,
        'is_enabled' => true,
        'quiet_hours_start' => '22:00:00',
        'quiet_hours_end' => '07:00:00',
        'timezone' => 'Africa/Cairo',
    ]);
    $dispatcher = Mockery::mock(DispatchNotificationAction::class);
    $dispatcher->shouldReceive('execute')->never();

    (new DispatchCampaignRecipientJob($campaign->id, $run->id, $customer->id))->handle($dispatcher);

    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Skipped)
        ->and($run->fresh()->completed_at)->not->toBeNull()
        ->and($run->fresh()->recipients_sent)->toBe(0)
        ->and(NotificationDispatch::query()->count())->toBe(0);
});

it('skips opted-out campaign recipients without creating a dispatch', function (): void {
    $customer = User::factory()->asCustomer()->create();
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
    NotificationPreference::factory()->create([
        'user_id' => $customer->id,
        'channel' => NotificationChannel::Email,
        'event_category' => EventCategory::Marketing,
        'is_enabled' => false,
    ]);
    $dispatcher = Mockery::mock(DispatchNotificationAction::class);
    $dispatcher->shouldReceive('execute')->never();

    (new DispatchCampaignRecipientJob($campaign->id, $run->id, $customer->id))->handle($dispatcher);

    expect($recipient->fresh()->status)->toBe(CampaignRecipientStatus::Skipped)
        ->and($run->fresh()->completed_at)->not->toBeNull()
        ->and(NotificationDispatch::query()->count())->toBe(0);
});

it('creates one unread admin inbox item per booking with a direct safe booking link', function (): void {
    $admin = User::factory()->asAdmin()->create();
    foreach (['view_any_admin::inbox', 'view_admin::inbox'] as $ability) {
        $admin->givePermissionTo(Permission::findOrCreate($ability, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->create(['customer_id' => $customer->id]);
    $vendor = VendorProfile::factory()->approved()->create([
        'business_name' => ['en' => 'QA Rentals', 'ar' => 'تأجير الاختبار'],
    ]);
    $bookingVendor = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
        'response_deadline' => now()->addHours(5),
    ]);
    $dispatcher = Mockery::mock(DispatchNotificationAction::class);
    $dispatcher->shouldReceive('execute')->zeroOrMoreTimes()->andReturn(null);
    $listener = new OnBookingSubmitted($dispatcher);
    $event = new BookingSubmittedToVendor($bookingVendor->load('vendor', 'booking.customer'), $booking->load('customer'));

    $listener->handle($event);
    $listener->handle($event);

    $item = AdminInboxItem::query()->where('source_type', 'booking')->where('source_id', $booking->id)->sole();
    expect($item->admin_id)->toBe($admin->id)
        ->and($item->status->value)->toBe('unread')
        ->and($item->getTranslation('body', 'en'))->toContain('QA Rentals')->toContain($bookingVendor->response_deadline->toIso8601String())
        ->and($item->getTranslation('body', 'ar'))->toContain('تأجير الاختبار')
        ->and(AdminInboxItem::query()->where('source_type', 'booking')->where('source_id', $booking->id)->count())->toBe(1);

    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(ListAdminInboxItems::class)
        ->assertSuccessful()
        ->assertTableActionVisible('viewBooking', $item)
        ->assertTableActionHasUrl(
            'viewBooking',
            BookingResource::getUrl('view', ['record' => $booking]),
            $item,
        );

    expect(BookingResource::getUrl('view', ['record' => $booking]))
        ->toContain($booking->public_id);
});
