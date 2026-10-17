<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Events\BookingCancelled;
use App\Modules\Booking\Domain\Events\BookingConfirmed;
use App\Modules\Booking\Domain\Events\VendorRejected;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Communication\Application\Actions\DispatchNotificationAction;
use App\Modules\Communication\Application\DTOs\DispatchNotificationDTO;
use App\Modules\Communication\Application\Listeners\OnBookingCancelled;
use App\Modules\Communication\Application\Listeners\OnBookingConfirmed;
use App\Modules\Communication\Application\Listeners\OnPaymentCaptured;
use App\Modules\Communication\Application\Listeners\OnRefundCompleted;
use App\Modules\Communication\Application\Listeners\OnVendorRejected;
use App\Modules\Communication\Database\Seeders\CustomerCoreInAppNotificationTemplateSeeder;
use App\Modules\Communication\Database\Seeders\NotificationTemplateSeeder;
use App\Modules\Communication\Domain\Enums\EventCategory;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\Models\NotificationPreference;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
use App\Modules\Communication\Infrastructure\Gateways\DatabaseNotificationAdapter;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Domain\Events\RefundCompleted;
use Illuminate\Support\Facades\Queue;

it('renders one customer inbox item per core event after listener replay', function (string $locale) {
    Queue::fake();
    app(CustomerCoreInAppNotificationTemplateSeeder::class)->run();
    $customer = User::factory()->asCustomer()->create(['preferred_locale' => $locale]);
    $booking = Booking::factory()->create(['customer_id' => $customer->id]);
    $bookingVendor = BookingVendor::factory()->create(['booking_id' => $booking->id]);
    $events = [
        'booking.confirmed' => [OnBookingConfirmed::class, new BookingConfirmed($booking)],
        'booking.cancelled' => [OnBookingCancelled::class, new BookingCancelled($booking)],
        'booking.rejected' => [OnVendorRejected::class, new VendorRejected($bookingVendor)],
        'payment.captured' => [OnPaymentCaptured::class, new PaymentCaptured(101, $booking->id, 2500, 'EGP', now())],
        'refund.completed' => [OnRefundCompleted::class, new RefundCompleted(201, 101, $booking->id, 2500, 'EGP', 'customer_request')],
    ];

    foreach ($events as $eventKey => [$listener, $event]) {
        app($listener)->handle($event);
        if ($eventKey === 'booking.cancelled') {
            $legacy = NotificationDispatch::query()->where('user_id', $customer->id)
                ->where('channel', NotificationChannel::InApp->value)
                ->whereHas('template', fn ($query) => $query->where('event_key', $eventKey))->firstOrFail();
            $context = $legacy->context;
            unset($context['event_identity'], $context['event_key'], $context['audience']);
            $legacy->update(['context' => $context]);
        }
        app($listener)->handle($event);
        $dispatches = NotificationDispatch::query()
            ->where('user_id', $customer->id)
            ->where('channel', NotificationChannel::InApp->value)
            ->whereHas('template', fn ($query) => $query->where('event_key', $eventKey))
            ->get();
        expect($dispatches)->toHaveCount(1);
        $dispatch = $dispatches->first();
        expect($dispatch->locale)->toBe($locale)
            ->and($dispatch->context['subject'])->not->toBeEmpty()
            ->and($dispatch->context['body'])->toContain($booking->public_id)->not->toContain('{{');
        app(DatabaseNotificationAdapter::class)->send($dispatch);
        $this->actingAs($customer)->get(route('storefront.account.notifications', ['locale' => $locale]))
            ->assertOk()->assertSeeText($dispatch->context['subject'])->assertSeeText($dispatch->context['body']);
    }

    // Separate refund occurrences for the same booking must not collapse.
    app(OnRefundCompleted::class)->handle(new RefundCompleted(202, 101, $booking->id, 1000, 'EGP', 'customer_request'));
    expect(NotificationDispatch::query()->where('user_id', $customer->id)
        ->where('channel', NotificationChannel::InApp->value)
        ->whereHas('template', fn ($query) => $query->where('event_key', 'refund.completed'))->count())->toBe(2);
})->with(['en', 'ar']);

it('adds missing core templates without replacing customer edits or reactivating them', function () {
    $seeder = app(CustomerCoreInAppNotificationTemplateSeeder::class);
    $seeder->run();
    $template = NotificationTemplate::query()->forEventChannelAudience(
        'booking.confirmed', NotificationChannel::InApp, NotificationAudience::Customer,
    )->firstOrFail();
    $template->update(['body' => ['en' => 'My edited confirmation', 'ar' => 'نص التأكيد المعدل'], 'is_active' => false]);
    $originalId = $template->public_id;
    $seeder->run();
    expect($template->refresh()->public_id)->toBe($originalId)
        ->and($template->getTranslation('body', 'en', false))->toBe('My edited confirmation')
        ->and($template->getTranslation('body', 'ar', false))->toBe('نص التأكيد المعدل')
        ->and($template->is_active)->toBeFalse();
    foreach (['booking.confirmed', 'booking.cancelled', 'booking.rejected', 'payment.captured', 'refund.completed'] as $eventKey) {
        expect(NotificationTemplate::query()->forEventChannelAudience(
            $eventKey, NotificationChannel::InApp, NotificationAudience::Customer,
        )->count())->toBe(1);
    }
});

it('renders a rejection notification in the current storefront locale', function (): void {
    Queue::fake();
    app(CustomerCoreInAppNotificationTemplateSeeder::class)->run();
    app(NotificationTemplateSeeder::class)->run();
    $customer = User::factory()->asCustomer()->create(['preferred_locale' => 'ar']);
    foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
        NotificationPreference::factory()->create([
            'user_id' => $customer->id,
            'channel' => $channel,
            'event_category' => EventCategory::Booking,
            'is_enabled' => true,
        ]);
    }
    $booking = Booking::factory()->create(['customer_id' => $customer->id]);
    $bookingVendor = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'rejection_reason' => ['en' => 'English rejection reason', 'ar' => 'سبب الرفض بالعربية'],
    ]);

    app(OnVendorRejected::class)->handle(new VendorRejected($bookingVendor));
    $dispatch = NotificationDispatch::query()
        ->where('user_id', $customer->id)
        ->where('channel', NotificationChannel::InApp->value)
        ->whereHas('template', fn ($query) => $query->where('event_key', 'booking.rejected'))
        ->firstOrFail();
    app(DatabaseNotificationAdapter::class)->send($dispatch);

    foreach ([NotificationChannel::Push, NotificationChannel::Email] as $channel) {
        $otherChannel = NotificationDispatch::query()
            ->where('user_id', $customer->id)
            ->where('channel', $channel->value)
            ->whereHas('template', fn ($query) => $query->where('event_key', 'booking.rejected'))
            ->firstOrFail();
        expect($otherChannel->context['body'])->toContain('سبب الرفض بالعربية')
            ->and($otherChannel->context['body'])->not->toContain('English rejection reason')
            ->and(collect(array_keys($otherChannel->context))
                ->filter(fn (string $key): bool => str_ends_with($key, '_translations'))
                ->all())->toBe([]);
    }

    $this->actingAs($customer)
        ->get(route('storefront.account.notifications', ['locale' => 'en']))
        ->assertOk()
        ->assertSeeText('English rejection reason')
        ->assertDontSeeText('سبب الرفض بالعربية');

    $this->actingAs($customer)
        ->get('/ar/account/notifications')
        ->assertOk()
        ->assertSeeText('سبب الرفض بالعربية')
        ->assertDontSeeText('English rejection reason');

    app(DispatchNotificationAction::class)->execute(new DispatchNotificationDTO(
        eventKey: 'campaign.direct',
        channel: NotificationChannel::Push,
        audience: NotificationAudience::Customer,
        eventCategory: EventCategory::Marketing,
        userId: $customer->id,
        context: ['body_translations' => ['en' => 'English', 'ar' => 'العربية']],
        directBody: 'Direct body',
    ));
    $direct = NotificationDispatch::query()->where('user_id', $customer->id)
        ->where('channel', NotificationChannel::Push->value)
        ->whereNull('notification_template_id')->latest('id')->firstOrFail();
    expect(collect(array_keys($direct->context))->filter(fn (string $key): bool => str_ends_with($key, '_translations'))->all())->toBe([]);

    app(DispatchNotificationAction::class)->execute(new DispatchNotificationDTO(
        eventKey: 'missing.event',
        channel: NotificationChannel::Email,
        audience: NotificationAudience::Customer,
        eventCategory: EventCategory::Marketing,
        userId: $customer->id,
        context: ['rejection_reason_translations' => ['en' => 'English', 'ar' => 'العربية']],
    ));
    $failed = NotificationDispatch::query()->where('user_id', $customer->id)
        ->where('channel', NotificationChannel::Email->value)
        ->where('status', 'failed')->latest('id')->firstOrFail();
    expect(collect(array_keys($failed->context))->filter(fn (string $key): bool => str_ends_with($key, '_translations'))->all())->toBe([]);
});
