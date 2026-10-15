<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Events\BookingCancelled;
use App\Modules\Booking\Domain\Events\BookingConfirmed;
use App\Modules\Booking\Domain\Events\VendorRejected;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Communication\Application\Listeners\OnBookingCancelled;
use App\Modules\Communication\Application\Listeners\OnBookingConfirmed;
use App\Modules\Communication\Application\Listeners\OnPaymentCaptured;
use App\Modules\Communication\Application\Listeners\OnRefundCompleted;
use App\Modules\Communication\Application\Listeners\OnVendorRejected;
use App\Modules\Communication\Database\Seeders\CustomerCoreInAppNotificationTemplateSeeder;
use App\Modules\Communication\Domain\Enums\NotificationAudience;
use App\Modules\Communication\Domain\Enums\NotificationChannel;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Communication\Domain\Models\NotificationTemplate;
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
