<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Listeners\ConfirmInventoryReservationsListener;
use App\Modules\Booking\Domain\Events\BookingConfirmed;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Application\Actions\CheckServiceAvailabilityAction;
use App\Modules\Catalog\Application\Actions\HoldServiceInventoryAction;
use App\Modules\Catalog\Application\Actions\ReleaseExpiredReservationsAction;
use App\Modules\Catalog\Domain\Enums\HoldType;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceDigitalDetail;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Identity\Domain\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Artisan;

it('does not let a hold expiring now block rental availability or a new hold', function () {
    $service = Service::factory()->rental()->create();
    $user = User::factory()->create();
    $startsAt = now()->addDay();
    $endsAt = $startsAt->copy()->addHours(4);
    $expiresAt = CarbonImmutable::now('UTC');
    $this->travelTo($expiresAt);
    $expired = ServiceInventoryReservation::factory()->create([
        'service_id' => $service->id,
        'product_type' => $service->product_type,
        'reserved_starts_at' => $startsAt,
        'reserved_ends_at' => $endsAt,
        'expires_at' => $expiresAt,
    ]);

    $availability = app(CheckServiceAvailabilityAction::class)->execute($service, $startsAt, $endsAt);
    $replacement = app(HoldServiceInventoryAction::class)->execute(
        $service,
        HoldType::Cart,
        $user->id,
        $startsAt,
        $endsAt,
    );

    expect($availability->available)->toBeTrue()
        ->and($replacement->status)->toBe(ReservationStatus::Held)
        ->and(app(ReleaseExpiredReservationsAction::class)->execute())->toBe(1)
        ->and($expired->refresh()->status)->toBe(ReservationStatus::Expired);

    $this->travelBack();
});

it('reconciles exact expiry for rental sale and digital holds', function (): void {
    $expiresAt = CarbonImmutable::now('UTC');
    $this->travelTo($expiresAt);
    $user = User::factory()->create();
    $rental = Service::factory()->rental()->create();
    $sale = Service::factory()->sale()->create();
    ServiceSaleDetail::factory()->create(['service_id' => $sale->id, 'stock_quantity' => 1]);
    $digital = Service::factory()->digital()->create();
    ServiceDigitalDetail::factory()->create(['service_id' => $digital->id]);
    $startsAt = now()->addDay();
    $endsAt = $startsAt->copy()->addHours(2);

    foreach ([$rental, $sale, $digital] as $service) {
        ServiceInventoryReservation::factory()->create([
            'service_id' => $service->id,
            'product_type' => $service->product_type,
            'reserved_starts_at' => $service->product_type === ProductType::Rental ? $startsAt : null,
            'reserved_ends_at' => $service->product_type === ProductType::Rental ? $endsAt : null,
            'expires_at' => $expiresAt,
        ]);

        $availability = app(CheckServiceAvailabilityAction::class)->execute(
            $service,
            $service->product_type === ProductType::Rental ? $startsAt : null,
            $service->product_type === ProductType::Rental ? $endsAt : null,
        );
        $replacement = app(HoldServiceInventoryAction::class)->execute(
            $service,
            HoldType::Cart,
            $user->id,
            $service->product_type === ProductType::Rental ? $startsAt : null,
            $service->product_type === ProductType::Rental ? $endsAt : null,
        );

        expect($availability->available)->toBeTrue()
            ->and($replacement->status)->toBe(ReservationStatus::Held);
    }

    expect(app(ReleaseExpiredReservationsAction::class)->execute())->toBe(3);
    $this->travelBack();
});

it('releases only held reservations atomically and is idempotent', function (): void {
    $expiresAt = CarbonImmutable::now('UTC');
    $this->travelTo($expiresAt);
    $user = User::factory()->create();
    $service = Service::factory()->rental()->create();
    $held = ServiceInventoryReservation::factory()->create([
        'service_id' => $service->id,
        'expires_at' => $expiresAt,
    ]);
    $confirmed = ServiceInventoryReservation::factory()->confirmed()->create([
        'service_id' => $service->id,
        'expires_at' => $expiresAt->subMinute(),
        'user_id' => $user->id,
    ]);

    expect(Artisan::call('booking:release-expired-reservations'))->toBe(0)
        ->and($held->refresh()->status)->toBe(ReservationStatus::Expired)
        ->and($confirmed->refresh()->status)->toBe(ReservationStatus::Confirmed)
        ->and(Artisan::call('booking:release-expired-reservations'))->toBe(0)
        ->and(Artisan::output())->toContain('Released 0 expired reservations.');

    $this->travelBack();
});

it('does not confirm a hold expiring now when a booking confirmation event is handled', function () {
    $expiresAt = CarbonImmutable::now('UTC');
    $this->travelTo($expiresAt);
    $booking = Booking::factory()->create();
    $bookingVendor = BookingVendor::factory()->create(['booking_id' => $booking->id]);
    $item = BookingItem::factory()->rental()->create(['booking_vendor_id' => $bookingVendor->id]);
    $hold = ServiceInventoryReservation::factory()->create([
        'service_id' => $item->service_id,
        'booking_item_id' => $item->id,
        'expires_at' => $expiresAt,
    ]);

    app(ConfirmInventoryReservationsListener::class)->handle(new BookingConfirmed($booking));

    expect($hold->refresh()->status)->toBe(ReservationStatus::Held);

    $this->travelBack();
});
