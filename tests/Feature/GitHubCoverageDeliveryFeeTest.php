<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\AddItemToBookingAction;
use App\Modules\Booking\Application\Actions\RemoveItemFromBookingAction;
use App\Modules\Booking\Application\DTOs\AddBookingItemDTO;
use App\Modules\Booking\Application\Listeners\RecalculateBookingTotalsListener;
use App\Modules\Booking\Domain\Events\BookingItemAdded;
use App\Modules\Booking\Domain\Events\BookingItemRemoved;
use App\Modules\Booking\Domain\Exceptions\CurrencyMismatchException;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAddress;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use Illuminate\Support\Facades\Event;

it('snapshots coverage delivery once per physical vendor and exempts digital delivery', function (ProductType $type): void {
    Event::fake([BookingItemAdded::class]);
    config()->set('catalog.digital_services_enabled', true);
    $city = City::factory()->create(['governorate_id' => Governorate::factory()->create(['transport_fee_minor' => 0])->id]);
    $booking = Booking::factory()->draft()->create();
    BookingAddress::factory()->create(['booking_id' => $booking->id, 'city_id' => $city->id]);
    $service = Service::factory()->published()->create(['product_type' => $type, 'base_price_minor' => 90000]);
    VendorCoverageArea::factory()->create([
        'vendor_profile_id' => $service->vendor_profile_id, 'city_id' => $city->id,
        'delivery_fee_minor' => 7500, 'delivery_fee_currency' => 'EGP',
        'min_order_minor' => 50000, 'min_order_currency' => 'EGP',
    ]);
    $item = app(AddItemToBookingAction::class)->execute(new AddBookingItemDTO(
        bookingId: $booking->id, customerId: $booking->customer_id, serviceId: $service->id,
        quantity: 1, effectiveStartsAt: null, effectiveEndsAt: null, customizationData: null,
    ));
    app(RecalculateBookingTotalsListener::class)->handle(new BookingItemAdded($item));
    $expectedFee = $type === ProductType::Digital ? 0 : 7500;
    expect($item->bookingVendor->fresh()->delivery_fee_minor)->toBe($expectedFee)
        ->and($booking->fresh()->delivery_total_minor)->toBe($expectedFee)
        ->and($booking->fresh()->total_minor)->toBe(90000 + $expectedFee);

    VendorCoverageArea::query()->where('vendor_profile_id', $service->vendor_profile_id)->update(['delivery_fee_minor' => 20000]);
    $anotherService = Service::factory()->published()->create([
        'vendor_profile_id' => $service->vendor_profile_id, 'product_type' => $type, 'base_price_minor' => 90000,
    ]);
    $another = app(AddItemToBookingAction::class)->execute(new AddBookingItemDTO(
        bookingId: $booking->id, customerId: $booking->customer_id, serviceId: $anotherService->id,
        quantity: 1, effectiveStartsAt: null, effectiveEndsAt: null, customizationData: null,
    ));
    app(RecalculateBookingTotalsListener::class)->handle(new BookingItemAdded($another));
    expect($booking->fresh()->delivery_total_minor)->toBe($expectedFee)
        ->and($booking->fresh()->total_minor)->toBe(180000 + $expectedFee);
})->with(ProductType::cases());

it('clears delivery after removing the last physical item from a mixed vendor cart', function (): void {
    Event::fake([BookingItemAdded::class, BookingItemRemoved::class]);
    config()->set('catalog.digital_services_enabled', true);
    $city = City::factory()->create(['governorate_id' => Governorate::factory()->create(['transport_fee_minor' => 0])->id]);
    $booking = Booking::factory()->draft()->create();
    BookingAddress::factory()->create(['booking_id' => $booking->id, 'city_id' => $city->id]);
    $sale = Service::factory()->sale()->published()->create(['base_price_minor' => 90000]);
    $digital = Service::factory()->digital()->published()->create(['vendor_profile_id' => $sale->vendor_profile_id, 'base_price_minor' => 10000]);
    VendorCoverageArea::factory()->create(['vendor_profile_id' => $sale->vendor_profile_id, 'city_id' => $city->id, 'delivery_fee_minor' => 7500]);
    foreach ([$digital, $sale] as $service) {
        $item = app(AddItemToBookingAction::class)->execute(new AddBookingItemDTO(
            bookingId: $booking->id, customerId: $booking->customer_id, serviceId: $service->id,
            quantity: 1, effectiveStartsAt: null, effectiveEndsAt: null, customizationData: null,
        ));
        app(RecalculateBookingTotalsListener::class)->handle(new BookingItemAdded($item));
    }
    expect($booking->fresh()->delivery_total_minor)->toBe(7500);
    app(RemoveItemFromBookingAction::class)->execute($booking->id, $booking->customer_id, $item->public_id);
    app(RecalculateBookingTotalsListener::class)->handle(new BookingItemRemoved($item->booking_vendor_id, $booking->id, $item->line_total_minor));
    expect($booking->fresh()->delivery_total_minor)->toBe(0)
        ->and($booking->fresh()->total_minor)->toBe(10000);
});

it('rejects a coverage fee currency mismatch without persisting cart items', function (): void {
    $booking = Booking::factory()->draft()->create();
    $address = BookingAddress::factory()->create(['booking_id' => $booking->id]);
    $service = Service::factory()->sale()->published()->create();
    VendorCoverageArea::factory()->create([
        'vendor_profile_id' => $service->vendor_profile_id, 'city_id' => $address->city_id,
        'delivery_fee_minor' => 7500, 'delivery_fee_currency' => 'USD',
    ]);
    expect(fn () => app(AddItemToBookingAction::class)->execute(new AddBookingItemDTO(
        bookingId: $booking->id, customerId: $booking->customer_id, serviceId: $service->id,
        quantity: 1, effectiveStartsAt: null, effectiveEndsAt: null, customizationData: null,
    )))->toThrow(CurrencyMismatchException::class);
    expect($booking->items()->count())->toBe(0)
        ->and($booking->vendors()->count())->toBe(0);
});

it('returns the recalculated total after removing the last physical delivery item', function (): void {
    $booking = Booking::factory()->draft()->create(['subtotal_minor' => 100000, 'delivery_total_minor' => 7500, 'total_minor' => 107500]);
    $vendor = BookingVendor::factory()->create(['booking_id' => $booking->id, 'subtotal_minor' => 100000, 'delivery_fee_minor' => 7500]);
    $sale = BookingItem::factory()->sale()->create(['booking_vendor_id' => $vendor->id, 'line_total_minor' => 90000]);
    BookingItem::factory()->digital()->create(['booking_vendor_id' => $vendor->id, 'line_total_minor' => 10000]);

    $result = app(RemoveItemFromBookingAction::class)->execute($booking->id, $booking->customer_id, $sale->public_id);

    expect($result['booking_total_minor'])->toBe(10000)
        ->and($booking->fresh()->delivery_total_minor)->toBe(0)
        ->and($booking->fresh()->total_minor)->toBe(10000);
});
