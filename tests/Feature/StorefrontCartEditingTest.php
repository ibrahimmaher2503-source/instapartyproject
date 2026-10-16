<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAddress;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Catalog\Domain\Enums\HoldType;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Catalog\Domain\States\ServiceStatus\ArchivedState;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\DB;

$makeCartFixture = static function (ProductType $type, bool $submitted = false): array {
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => $submitted ? VendorReviewState::class : DraftState::class,
        'subtotal_minor' => 90000,
        'total_minor' => 90000,
    ]);
    BookingAddress::factory()->create(['booking_id' => $booking->id]);

    $serviceFactory = match ($type) {
        ProductType::Rental => Service::factory()->rental(),
        ProductType::Sale => Service::factory()->sale(),
        ProductType::Digital => Service::factory()->digital(),
    };
    $service = $serviceFactory->published()->create(['base_price_minor' => 90000]);
    match ($type) {
        ProductType::Sale => ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 10]),
        ProductType::Rental, ProductType::Digital => null,
    };

    $vendor = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $service->vendor_profile_id,
        'subtotal_minor' => 90000,
    ]);
    $itemFactory = match ($type) {
        ProductType::Rental => BookingItem::factory()->rental(),
        ProductType::Sale => BookingItem::factory()->sale(),
        ProductType::Digital => BookingItem::factory()->digital(),
    };
    $item = $itemFactory->create([
        'booking_vendor_id' => $vendor->id,
        'service_id' => $service->id,
        'unit_price_minor' => 90000,
        'unit_price_currency' => 'EGP',
        'line_total_minor' => 90000,
        'line_total_currency' => 'EGP',
        'quantity' => 1,
        'effective_starts_at' => $booking->event_starts_at,
        'effective_ends_at' => $booking->event_ends_at,
    ]);

    $reservation = match ($type) {
        ProductType::Rental, ProductType::Sale => ServiceInventoryReservation::factory()->create([
            'service_id' => $service->id,
            'user_id' => $customer->id,
            'product_type' => $type,
            'hold_type' => HoldType::Cart,
            'status' => ReservationStatus::Held,
            'reserved_starts_at' => match ($type) {
                ProductType::Rental => $item->effective_starts_at,
                ProductType::Sale, ProductType::Digital => null,
            },
            'reserved_ends_at' => match ($type) {
                ProductType::Rental => $item->effective_ends_at,
                ProductType::Sale, ProductType::Digital => null,
            },
            'quantity' => 1,
            'expires_at' => now()->addMinutes(15),
            'booking_item_id' => $item->id,
        ]),
        ProductType::Digital => null,
    };

    return compact('customer', 'booking', 'item', 'service', 'reservation');
};

it('updates sale and digital quantities with snapshot prices and replay-safe totals', function (ProductType $type) use ($makeCartFixture): void {
    ['customer' => $customer, 'booking' => $booking, 'item' => $item, 'reservation' => $reservation] = $makeCartFixture($type);
    $url = "/en/cart/{$booking->public_id}/items/{$item->public_id}";

    $this->actingAs($customer)->get('/en/cart')
        ->assertOk()
        ->assertSee($url, false)
        ->assertSee('name="quantity"', false)
        ->assertSee(__('storefront.checkout.summary.submit'));

    $this->actingAs($customer)->put($url, ['quantity' => 2])->assertRedirect('/en/cart');
    $this->put($url, ['quantity' => 2])->assertRedirect('/en/cart');

    expect($item->fresh()->quantity)->toBe(2)
        ->and($item->fresh()->line_total_minor)->toBe(180000)
        ->and($booking->fresh()->subtotal_minor)->toBe(180000)
        ->and($booking->fresh()->total_minor)->toBe(180000)
        ->and(DB::table('service_inventory_reservations')->where('booking_item_id', $item->id)->count())
        ->toBe(match ($type) {
            ProductType::Sale => 1,
            ProductType::Digital => 0,
            ProductType::Rental => 0,
        });

    match ($type) {
        ProductType::Sale => expect($reservation?->fresh()?->quantity)->toBe(2),
        ProductType::Digital => expect($reservation)->toBeNull(),
        ProductType::Rental => null,
    };
})->with([ProductType::Sale, ProductType::Digital]);

it('renders a localized cart quantity label instead of the raw translation key', function () use ($makeCartFixture): void {
    ['customer' => $customer] = $makeCartFixture(ProductType::Sale);

    $this->actingAs($customer)->get('/en/cart')
        ->assertOk()
        ->assertSee(__('booking.proposal_kind.change_quantity', [], 'en'))
        ->assertDontSee('booking.actions.change_quantity');

    $this->get('/ar/cart')
        ->assertOk()
        ->assertSee(__('booking.proposal_kind.change_quantity', [], 'ar'))
        ->assertDontSee('booking.actions.change_quantity');
});

it('keeps rental quantity fixed while preserving its existing value', function () use ($makeCartFixture): void {
    ['customer' => $customer, 'booking' => $booking, 'item' => $item, 'reservation' => $reservation] = $makeCartFixture(ProductType::Rental);
    $item->update(['quantity' => 3, 'line_total_minor' => 270000]);
    $item->bookingVendor->update(['subtotal_minor' => 270000]);
    $booking->update(['subtotal_minor' => 270000, 'total_minor' => 270000]);
    $url = "/en/cart/{$booking->public_id}/items/{$item->public_id}";

    $this->actingAs($customer)->get('/en/cart')->assertOk()
        ->assertDontSee('name="quantity"', false)
        ->assertSee(__('storefront.service.quantity').': 3');
    $this->from('/en/cart')->put($url, ['quantity' => 2])->assertRedirect('/en/cart')->assertSessionHasErrors('quantity');
    $this->put($url, ['quantity' => 3])->assertRedirect('/en/cart');

    expect($item->fresh()->quantity)->toBe(3)
        ->and($item->fresh()->line_total_minor)->toBe(270000)
        ->and($booking->fresh()->total_minor)->toBe(270000)
        ->and($reservation?->fresh()?->quantity)->toBe(1);
});

it('reduces an active sale hold from its booking snapshot even if the service is no longer published', function () use ($makeCartFixture): void {
    ['customer' => $customer, 'booking' => $booking, 'item' => $item, 'service' => $service, 'reservation' => $reservation] = $makeCartFixture(ProductType::Sale);
    $item->update(['quantity' => 3, 'line_total_minor' => 270000]);
    $item->bookingVendor->update(['subtotal_minor' => 270000]);
    $booking->update(['subtotal_minor' => 270000, 'total_minor' => 270000]);
    $reservation->update(['quantity' => 3]);
    $service->update(['status' => ArchivedState::$name]);

    $this->actingAs($customer)->put("/en/cart/{$booking->public_id}/items/{$item->public_id}", ['quantity' => 2])
        ->assertRedirect('/en/cart');

    expect($item->fresh()->quantity)->toBe(2)
        ->and($item->fresh()->line_total_minor)->toBe(180000)
        ->and($reservation?->fresh()?->quantity)->toBe(2)
        ->and($booking->fresh()->total_minor)->toBe(180000);
});

it('reclaims an expired or released sale hold without counting the old hold twice', function (string $holdState) use ($makeCartFixture): void {
    ['customer' => $customer, 'booking' => $booking, 'item' => $item, 'service' => $service, 'reservation' => $reservation] = $makeCartFixture(ProductType::Sale);
    $reservation->update(match ($holdState) {
        'expired' => ['quantity' => 5, 'expires_at' => now()->subMinute()],
        'released' => ['quantity' => 5, 'status' => ReservationStatus::Released],
    });

    $this->actingAs($customer)->put("/en/cart/{$booking->public_id}/items/{$item->public_id}", ['quantity' => 2])
        ->assertRedirect('/en/cart');

    $activeReservations = DB::table('service_inventory_reservations')
        ->where('service_id', $service->id)
        ->where('status', ReservationStatus::Held->value)
        ->where('expires_at', '>', now());
    expect($activeReservations->count())->toBe(1)
        ->and((int) $activeReservations->sum('quantity'))->toBe(2)
        ->and($item->fresh()->quantity)->toBe(2)
        ->and($booking->fresh()->total_minor)->toBe(180000);
})->with(['expired', 'released']);

it('rejects cart quantity edits by another customer and after submission', function () use ($makeCartFixture): void {
    ['customer' => $customer, 'booking' => $booking, 'item' => $item] = $makeCartFixture(ProductType::Sale);
    $url = "/en/cart/{$booking->public_id}/items/{$item->public_id}";

    $this->actingAs(User::factory()->asCustomer()->create())->put($url, ['quantity' => 2])->assertNotFound();
    ['customer' => $customer, 'booking' => $booking, 'item' => $item] = $makeCartFixture(ProductType::Sale, submitted: true);
    $this->actingAs($customer)->put("/en/cart/{$booking->public_id}/items/{$item->public_id}", ['quantity' => 2])
        ->assertConflict();

    expect($item->fresh()->quantity)->toBe(1)->and($item->fresh()->line_total_minor)->toBe(90000);
});

it('validates quantity bounds before changing a draft cart', function (int $quantity) use ($makeCartFixture): void {
    ['customer' => $customer, 'booking' => $booking, 'item' => $item, 'reservation' => $reservation] = $makeCartFixture(ProductType::Sale);

    $this->actingAs($customer)->from('/en/cart')->put("/en/cart/{$booking->public_id}/items/{$item->public_id}", ['quantity' => $quantity])
        ->assertRedirect('/en/cart')->assertSessionHasErrors('quantity');

    expect($item->fresh()->quantity)->toBe(1)
        ->and($reservation?->fresh()?->quantity)->toBe(1)
        ->and($booking->fresh()->total_minor)->toBe(90000);
})->with([0, 101]);

it('removes rental, sale, and digital cart items through the storefront route', function (ProductType $type) use ($makeCartFixture): void {
    ['customer' => $customer, 'booking' => $booking, 'item' => $item, 'reservation' => $reservation] = $makeCartFixture($type);

    $this->actingAs($customer)->delete("/en/cart/{$booking->public_id}/items/{$item->public_id}")
        ->assertRedirect('/en/cart');

    expect($item->fresh())->toBeNull()->and($booking->fresh()->total_minor)->toBe(0);
    match ($type) {
        ProductType::Rental, ProductType::Sale => expect($reservation?->fresh()?->status)->toBe(ReservationStatus::Released),
        ProductType::Digital => expect($reservation)->toBeNull(),
    };
})->with(ProductType::cases());
