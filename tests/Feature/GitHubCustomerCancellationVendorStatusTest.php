<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\CancelBookingByCustomerAction;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingCancelled;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingSnapshot;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Application\Services\VendorDashboardSummaryService;
use App\Modules\Shared\Filament\Vendor\Widgets\VendorRecentBookingsWidget;
use App\Modules\Shared\Filament\Vendor\Widgets\VendorStatsOverviewWidget;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
});

/** @return array{User, VendorProfile, Booking, BookingVendor, BookingItem, ServiceInventoryReservation} */
function pendingBookingForCustomerCancellation(ProductType $type): array
{
    $customer = User::factory()->phoneVerified()->asCustomer()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => VendorReviewState::class,
        'submitted_at' => now(),
    ]);
    $bookingVendor = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
        'sub_status' => VendorSubStatus::Pending,
    ]);

    $itemFactory = BookingItem::factory();
    $item = match ($type) {
        ProductType::Rental => $itemFactory->rental()->create(['booking_vendor_id' => $bookingVendor->id]),
        ProductType::Sale => $itemFactory->sale()->create(['booking_vendor_id' => $bookingVendor->id]),
        ProductType::Digital => $itemFactory->digital()->create(['booking_vendor_id' => $bookingVendor->id]),
    };

    $reservation = ServiceInventoryReservation::factory()->create([
        'service_id' => $item->getAttribute('service_id'),
        'user_id' => $customer->id,
        'product_type' => $type,
        'booking_item_id' => $item->id,
    ]);

    return [$customer, $vendor, $booking, $bookingVendor, $item, $reservation];
}

it('cancels pending vendor allocations and displays the cancelled status for each product type', function (ProductType $type): void {
    [$customer, $vendor, $booking, $bookingVendor, $item, $reservation] = pendingBookingForCustomerCancellation($type);
    $this->actingAs($vendor->user, 'vendor');

    Livewire::test(VendorRecentBookingsWidget::class)
        ->assertCanSeeTableRecords([$bookingVendor])
        ->assertSee(__('booking.vendor_status.pending'));

    app(CancelBookingByCustomerAction::class)->execute($booking, $customer->id, 'customer requested cancellation');

    expect($booking->refresh()->lifecycle_status->getValue())->toBe('cancelled')
        ->and($bookingVendor->refresh()->sub_status)->toBe(VendorSubStatus::Cancelled)
        ->and($bookingVendor->responded_at)->not->toBeNull()
        ->and($reservation->refresh()->getAttribute('status'))->toBe(ReservationStatus::Released)
        ->and($reservation->release_reason)->toBe('booking_cancelled')
        ->and(DB::table('payments')->where('booking_id', $booking->id)->count())->toBe(0)
        ->and(DB::table('refunds')->where('booking_id', $booking->id)->count())->toBe(0)
        ->and(BookingSnapshot::query()->where('booking_id', $booking->id)->where('trigger_kind', 'booking_cancelled')->exists())->toBeTrue();

    Livewire::test(VendorRecentBookingsWidget::class)
        ->assertCanSeeTableRecords([$bookingVendor])
        ->assertSee(__('booking.vendor_status.cancelled'))
        ->assertDontSee(__('booking.vendor_status.pending'));
})->with([
    'rental' => [ProductType::Rental],
    'sale' => [ProductType::Sale],
    'digital' => [ProductType::Digital],
]);

it('removes a cancelled request from warmed vendor pending-booking dashboard metrics', function (): void {
    [$customer, $vendor, $booking] = pendingBookingForCustomerCancellation(ProductType::Rental);
    $secondVendor = VendorProfile::factory()->approved()->create();
    $secondAllocation = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $secondVendor->id,
        'sub_status' => VendorSubStatus::Pending,
    ]);

    foreach ([$vendor, $secondVendor] as $profile) {
        app()->setLocale('en');
        $statsKey = "vendor_dashboard_stats_{$profile->id}_en";
        Cache::forget($statsKey);
        Cache::forget("vendor_dashboard_summary_api_{$profile->id}");

        $this->actingAs($profile->user, 'vendor');
        Livewire::test(VendorStatsOverviewWidget::class)
            ->assertSeeInOrder([__('vendor-portal.dashboard.pending_bookings'), '1']);
        expect(app(VendorDashboardSummaryService::class)->summarize($profile)['action_required']['pending_bookings'])->toBe(1);
        expect(Cache::get($statsKey)['pendingBookings'])->toBe(1);
    }

    app(CancelBookingByCustomerAction::class)->execute($booking, $customer->id);

    expect($secondAllocation->refresh()->sub_status)->toBe(VendorSubStatus::Cancelled);
    foreach ([$vendor, $secondVendor] as $profile) {
        expect(Cache::has("vendor_dashboard_stats_{$profile->id}_en"))->toBeFalse()
            ->and(Cache::has("vendor_dashboard_summary_api_{$profile->id}"))->toBeFalse();

        $this->actingAs($profile->user, 'vendor');
        Livewire::test(VendorStatsOverviewWidget::class)
            ->assertSeeInOrder([__('vendor-portal.dashboard.pending_bookings'), '0']);
        expect(app(VendorDashboardSummaryService::class)->summarize($profile)['action_required']['pending_bookings'])->toBe(0);
    }
});

it('dispatches cancellation effects even when dashboard cache invalidation fails', function (): void {
    app()->setLocale('en');
    [$customer, $vendor, $booking, , , $reservation] = pendingBookingForCustomerCancellation(ProductType::Rental);
    $eventDispatched = false;

    Event::listen(BookingCancelled::class, function (BookingCancelled $event) use (&$eventDispatched, $booking): void {
        $eventDispatched = $event->booking->is($booking);
    });
    Cache::shouldReceive('forget')
        ->once()
        ->with("vendor_dashboard_stats_{$vendor->id}_en")
        ->andThrow(new RuntimeException('cache backend unavailable'));
    Cache::shouldReceive('forget')
        ->once()
        ->with("vendor_dashboard_stats_{$vendor->id}_ar")
        ->andReturnFalse();
    Cache::shouldReceive('forget')
        ->once()
        ->with("vendor_dashboard_summary_api_{$vendor->id}")
        ->andReturnFalse();
    Log::shouldReceive('warning')
        ->once()
        ->with('Vendor dashboard cache invalidation failed after booking cancellation.', [
            'booking_id' => $booking->id,
            'exception' => RuntimeException::class,
        ]);

    app(CancelBookingByCustomerAction::class)->execute($booking, $customer->id);

    expect($eventDispatched)->toBeTrue()
        ->and($reservation->refresh()->getAttribute('status'))->toBe(ReservationStatus::Released)
        ->and(BookingSnapshot::query()
            ->where('booking_id', $booking->id)
            ->where('trigger_kind', 'booking_cancelled')
            ->exists())->toBeTrue();
});

it('does not cancel a booking when the supplied customer does not own it', function (): void {
    [$customer, $vendor, $booking, $bookingVendor] = pendingBookingForCustomerCancellation(ProductType::Sale);
    $otherCustomer = User::factory()->phoneVerified()->asCustomer()->create();

    try {
        app(CancelBookingByCustomerAction::class)->execute($booking, $otherCustomer->id);
        $this->fail('Expected a not-found response for a customer who does not own the booking.');
    } catch (HttpException $exception) {
        expect($exception->getStatusCode())->toBe(404);
    }

    expect($booking->refresh()->lifecycle_status->getValue())->toBe('vendor_review')
        ->and($bookingVendor->refresh()->sub_status)->toBe(VendorSubStatus::Pending);
});
