<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\ExpireStaleVendorReviewsAction;
use App\Modules\Booking\Database\Factories\BookingVendorFactory;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Communication\Application\Listeners\OnVendorResponseTimedOut;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

function vendorReviewBooking(CarbonImmutable $eventStartsAt): Booking
{
    return Booking::factory()->create([
        'lifecycle_status' => VendorReviewState::class,
        'submitted_at' => now()->subHour(),
        'event_starts_at' => $eventStartsAt,
        'event_ends_at' => $eventStartsAt->addHours(4),
    ]);
}

function attachVendor(Booking $booking, VendorSubStatus $status, ?CarbonImmutable $deadline): BookingVendor
{
    return BookingVendorFactory::new()->create([
        'booking_id' => $booking->id,
        'sub_status' => $status,
        'response_deadline' => $deadline,
        'responded_at' => $status === VendorSubStatus::Pending ? null : now()->subMinute(),
    ]);
}

it('expires a pending vendor exactly at the UTC deadline and escalates to customer review', function (): void {
    $now = CarbonImmutable::parse('2026-09-06 10:00:00', 'UTC');
    $booking = vendorReviewBooking($now->addDay());
    $vendor = attachVendor($booking, VendorSubStatus::Pending, $now);

    $result = app(ExpireStaleVendorReviewsAction::class)->execute(asOf: $now);

    expect($result)->toMatchArray(['bookings' => 1, 'vendors' => 1, 'customer_review' => 1, 'cancelled' => 0])
        ->and($vendor->refresh()->sub_status)->toBe(VendorSubStatus::TimedOut)
        ->and($vendor->responded_at?->toISOString())->toBe($now->toISOString())
        ->and($booking->refresh()->lifecycle_status->getValue())->toBe(LifecycleStatus::CustomerReview->value)
        ->and(DB::table('state_transitions')->where('transitionable_type', BookingVendor::class)
            ->where('transitionable_id', $vendor->id)->where('to_state', 'timed_out')->count())->toBe(1)
        ->and(DB::table('audit_logs')->where('auditable_type', BookingVendor::class)
            ->where('auditable_id', $vendor->id)->where('action', 'booking.vendor_response_timed_out')->count())->toBe(1);
});

it('does not expire a pending vendor before the UTC deadline', function (): void {
    $now = CarbonImmutable::parse('2026-09-06 10:00:00', 'UTC');
    $booking = vendorReviewBooking($now->addDay());
    $vendor = attachVendor($booking, VendorSubStatus::Pending, $now->addSecond());

    $result = app(ExpireStaleVendorReviewsAction::class)->execute(asOf: $now);

    expect($result['bookings'])->toBe(0)
        ->and($vendor->refresh()->sub_status)->toBe(VendorSubStatus::Pending)
        ->and($booking->refresh()->lifecycle_status->getValue())->toBe(LifecycleStatus::VendorReview->value);
});

it('cancels vendor review when the event start boundary has arrived', function (): void {
    $now = CarbonImmutable::parse('2026-09-06 10:00:00', 'UTC');
    $booking = vendorReviewBooking($now);
    $vendor = attachVendor($booking, VendorSubStatus::Pending, $now->addHours(4));

    $result = app(ExpireStaleVendorReviewsAction::class)->execute(asOf: $now);

    expect($result)->toMatchArray(['bookings' => 1, 'vendors' => 1, 'customer_review' => 0, 'cancelled' => 1])
        ->and($vendor->refresh()->sub_status)->toBe(VendorSubStatus::TimedOut)
        ->and($booking->refresh()->lifecycle_status->getValue())->toBe(LifecycleStatus::Cancelled->value)
        ->and($booking->cancelled_at?->toISOString())->toBe($now->toISOString())
        ->and($booking->cancelled_by)->toBeNull();
});

it('preserves partial vendor responses and waits for future pending allocations', function (): void {
    $now = CarbonImmutable::parse('2026-09-06 10:00:00', 'UTC');
    $booking = vendorReviewBooking($now->addDay());
    $accepted = attachVendor($booking, VendorSubStatus::Accepted, $now->subHour());
    $expired = attachVendor($booking, VendorSubStatus::Pending, $now->subSecond());
    $future = attachVendor($booking, VendorSubStatus::Pending, $now->addHour());

    $result = app(ExpireStaleVendorReviewsAction::class)->execute(asOf: $now);

    expect($result)->toMatchArray(['bookings' => 1, 'vendors' => 1, 'customer_review' => 0, 'cancelled' => 0])
        ->and($accepted->refresh()->sub_status)->toBe(VendorSubStatus::Accepted)
        ->and($expired->refresh()->sub_status)->toBe(VendorSubStatus::TimedOut)
        ->and($future->refresh()->sub_status)->toBe(VendorSubStatus::Pending)
        ->and($booking->refresh()->lifecycle_status->getValue())->toBe(LifecycleStatus::VendorReview->value);
});

it('moves a partial response booking to customer review after the final pending vendor times out', function (): void {
    $now = CarbonImmutable::parse('2026-09-06 10:00:00', 'UTC');
    $booking = vendorReviewBooking($now->addDay());
    attachVendor($booking, VendorSubStatus::Accepted, $now->subHour());
    attachVendor($booking, VendorSubStatus::Pending, $now->subSecond());

    app(ExpireStaleVendorReviewsAction::class)->execute(asOf: $now);

    expect($booking->refresh()->lifecycle_status->getValue())->toBe(LifecycleStatus::CustomerReview->value);
});

it('is idempotent across repeated worker runs', function (): void {
    $now = CarbonImmutable::parse('2026-09-06 10:00:00', 'UTC');
    $booking = vendorReviewBooking($now->addDay());
    $vendor = attachVendor($booking, VendorSubStatus::Pending, $now);

    $first = app(ExpireStaleVendorReviewsAction::class)->execute(asOf: $now);
    $second = app(ExpireStaleVendorReviewsAction::class)->execute(asOf: $now);

    expect($first['vendors'])->toBe(1)
        ->and($second)->toBe(['bookings' => 0, 'vendors' => 0, 'customer_review' => 0, 'cancelled' => 0])
        ->and(DB::table('state_transitions')->where('transitionable_type', BookingVendor::class)
            ->where('transitionable_id', $vendor->id)->where('to_state', 'timed_out')->count())->toBe(1)
        ->and(DB::table('audit_logs')->where('auditable_type', BookingVendor::class)
            ->where('auditable_id', $vendor->id)->where('action', 'booking.vendor_response_timed_out')->count())->toBe(1);
});

it('keeps the production command fail closed until explicitly enabled', function (): void {
    config()->set('booking.vendor_review_expiry.enabled', false);
    $now = CarbonImmutable::parse('2026-09-06 10:00:00', 'UTC');
    $booking = vendorReviewBooking($now->addDay());
    $vendor = attachVendor($booking, VendorSubStatus::Pending, $now->subSecond());

    $this->artisan('booking:expire-stale-vendor-reviews')
        ->expectsOutput('Vendor-review expiry is disabled.')
        ->assertSuccessful();

    expect($vendor->refresh()->sub_status)->toBe(VendorSubStatus::Pending)
        ->and($booking->refresh()->lifecycle_status->getValue())->toBe(LifecycleStatus::VendorReview->value);
});

it('configures queued timeout handling with bounded retries', function (): void {
    $reflection = new ReflectionClass(OnVendorResponseTimedOut::class);

    expect($reflection->implementsInterface(ShouldQueue::class))->toBeTrue()
        ->and($reflection->getDefaultProperties()['tries'])->toBe(3)
        ->and($reflection->getMethod('backoff')->invoke($reflection->newInstanceWithoutConstructor()))->toBe([60, 300, 900]);
});

it('schedules stale vendor review expiry every minute', function (): void {
    $event = collect(app(Schedule::class)->events())
        ->first(fn ($event): bool => str_contains($event->command ?? '', 'booking:expire-stale-vendor-reviews'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('* * * * *');
});
