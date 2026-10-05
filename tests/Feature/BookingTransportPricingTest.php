<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\AddTransportationAdjustmentAction;
use App\Modules\Booking\Application\Actions\RecalculateBookingTotalsAction;
use App\Modules\Booking\Application\Services\BookingTransportPricingService;
use App\Modules\Booking\Database\Factories\BookingAddressFactory;
use App\Modules\Booking\Database\Factories\BookingVendorFactory;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

it('snapshots governorate transport pricing and reconciles an auditable invoice line', function (): void {
    $governorate = Governorate::factory()->create(['transport_fee_minor' => 2500]);
    $city = City::factory()->create(['governorate_id' => $governorate->id]);
    $booking = Booking::factory()->create();
    BookingAddressFactory::new()->create(['booking_id' => $booking->id, 'city_id' => $city->id]);
    BookingVendorFactory::new()->create([
        'booking_id' => $booking->id,
        'subtotal_minor' => 10_000,
        'delivery_fee_minor' => 500,
    ]);

    app(BookingTransportPricingService::class)->snapshotBaseFee($booking->fresh());
    app(RecalculateBookingTotalsAction::class)->execute($booking->fresh());

    expect($booking->fresh()->transport_base_minor)->toBe(2500)
        ->and($booking->fresh()->transport_base_source)->toBe('governorate:'.$governorate->code)
        ->and($booking->fresh()->total_minor)->toBe(13_000);

    $admin = User::factory()->create();
    $adjustment = app(AddTransportationAdjustmentAction::class)->execute(
        $booking->fresh(),
        $admin->id,
        1_000,
        'invoice:manual-transport',
        'INV-42',
    );

    expect($adjustment->amount_minor)->toBe(1_000)
        ->and($adjustment->source)->toBe('invoice:manual-transport')
        ->and($booking->fresh()->total_minor)->toBe(14_000)
        ->and(DB::table('audit_logs')
            ->where('action', 'booking_transportation_adjustment_added')
            ->where('auditable_id', $adjustment->id)
            ->exists())->toBeTrue();
});
