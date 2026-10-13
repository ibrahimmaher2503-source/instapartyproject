<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\AddTransportationAdjustmentAction;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAddress;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Domain\Models\User;
use Brick\Money\Money;

it('renders a printable bilingual invoice breakdown with transport lines', function (string $locale): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');
    $governorate = Governorate::factory()->create(['transport_fee_minor' => 2500]);
    $city = City::factory()->create(['governorate_id' => $governorate->id]);
    $booking = Booking::factory()->create([
        'subtotal_minor' => 10_000,
        'delivery_total_minor' => 4_000,
        'total_minor' => 14_000,
        'transport_base_minor' => 2_500,
        'transport_base_currency' => 'EGP',
        'transport_base_source' => 'governorate:'.$governorate->code,
    ]);
    BookingAddress::factory()->create(['booking_id' => $booking->id, 'city_id' => $city->id]);
    app(AddTransportationAdjustmentAction::class)->execute($booking, $admin->id, 1_000, 'invoice:manual-transport', 'INV-42');
    $booking->refresh()->update(['delivery_total_minor' => 4_000, 'total_minor' => 14_000]);

    $this->actingAs($admin, 'web')
        ->get(route('admin.bookings.invoice', [$booking->public_id, 'lang' => $locale]))
        ->assertOk()
        ->assertSee(__('booking.invoice.title', [], $locale))
        ->assertSee(__('booking.columns.transport_base', [], $locale))
        ->assertSee(__('booking.columns.transportation_adjustment', [], $locale))
        ->assertSee('invoice:manual-transport')
        ->assertSee(__('booking.invoice.recorded_delivery_adjustment', [], $locale))
        ->assertSee(Money::ofMinor(500, 'EGP')->formatTo($locale))
        ->assertSee(__('booking.invoice.print', [], $locale));
})->with(['en', 'ar']);
