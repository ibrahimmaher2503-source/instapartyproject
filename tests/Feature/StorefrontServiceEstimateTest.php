<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;

it('estimates the booking unit price for each product and locale without creating a hold', function (string $type, string $locale): void {
    $service = Service::factory()->{$type}()->published()->create(['base_price_minor' => 25050, 'base_price_currency' => 'EGP']);
    if ($type === 'rental') {
        ServiceRentalDetail::factory()->create(['service_id' => $service->id, 'default_rental_duration_hours' => 4]);
    } elseif ($type === 'sale') {
        ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 10]);
    }
    $quantity = $type === 'rental' ? 1 : 3;
    $query = ['quantity' => $quantity, 'event_starts_at' => now()->addDays(5)->format('Y-m-d\TH:i'), 'timezone' => 'Africa/Cairo'];
    $this->get("/{$locale}/services/{$service->public_id}?".http_build_query($query))
        ->assertOk()->assertViewHas('estimate', fn ($estimate): bool => $estimate->getMinorAmount()->toInt() === 25050 * $quantity)
        ->assertViewHas('availability', fn ($availability): bool => $availability->available)
        ->assertSee('quantity='.$quantity, false)->assertSee('event_starts_at=', false);
    expect(ServiceInventoryReservation::query()->count())->toBe(0);
})->with(['rental', 'sale', 'digital'])->with(['en', 'ar']);

it('keeps sale service details free of rental availability UI in both storefront locales', function (string $locale): void {
    $service = Service::factory()->sale()->published()->create();

    $this->get(route('storefront.services.show', [
        'locale' => $locale,
        'servicePublicId' => $service->public_id,
    ]))
        ->assertOk()
        ->assertSee(__('storefront.service.estimate_amount', [], $locale))
        ->assertDontSee(__('storefront.service.estimate_title', [], $locale))
        ->assertDontSee(__('storefront.service.estimate_note', [], $locale))
        ->assertDontSee(__('storefront.service.rental_estimate_note', [], $locale))
        ->assertDontSee(__('storefront.service.availability_available_rental', [], $locale))
        ->assertDontSee(__('storefront.service.availability_available_sale', [], $locale))
        ->assertDontSee('data-service-check-availability', false)
        ->assertDontSee('data-service-availability-status', false)
        ->assertDontSee('data-availability-endpoint', false);
})->with(['en', 'ar']);

it('shows unavailable rental windows and insufficient sale stock without reserving inventory', function (): void {
    $rental = Service::factory()->rental()->published()->create();
    ServiceRentalDetail::factory()->create(['service_id' => $rental->id, 'default_rental_duration_hours' => 4]);
    $start = now()->addDays(5)->startOfHour();
    ServiceInventoryReservation::factory()->create(['service_id' => $rental->id, 'product_type' => 'rental',
        'status' => 'confirmed', 'reserved_starts_at' => $start, 'reserved_ends_at' => $start->copy()->addHours(4)]);
    $this->get('/en/services/'.$rental->public_id.'?'.http_build_query(['event_starts_at' => $start->toIso8601String(), 'timezone' => 'UTC']))
        ->assertOk()->assertViewHas('availability', fn ($availability): bool => ! $availability->available);
    $sale = Service::factory()->sale()->published()->create();
    ServiceSaleDetail::factory()->create(['service_id' => $sale->id, 'stock_quantity' => 2]);
    $this->get('/en/services/'.$sale->public_id.'?quantity=3')
        ->assertOk()->assertViewHas('availability', fn ($availability): bool => ! $availability->available);
    expect(ServiceInventoryReservation::query()->count())->toBe(1);
});

it('rejects invalid estimate inputs before showing a quote', function (array $query, string $field): void {
    $service = Service::factory()->sale()->published()->create();
    $this->getJson('/en/services/'.$service->public_id.'?'.http_build_query($query))
        ->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    [['quantity' => 0], 'quantity'], [['quantity' => 101], 'quantity'], [['quantity' => '1.5'], 'quantity'],
    [['event_starts_at' => 'bad-date'], 'event_starts_at'], [['timezone' => 'bad-zone'], 'timezone'],
    [['event_starts_at' => '2000-01-01T10:00'], 'event_starts_at'],
    [['event_ends_at' => '2099-01-01T10:00'], 'event_starts_at'],
    [['event_starts_at' => '2099-01-01T10:00', 'event_ends_at' => '2099-01-01T09:00'], 'event_ends_at'],
]);

it('rejects a rental quote quantity the booking flow cannot accept', function (): void {
    $service = Service::factory()->rental()->published()->create();
    ServiceRentalDetail::factory()->create(['service_id' => $service->id, 'default_rental_duration_hours' => 4]);

    $this->getJson('/en/services/'.$service->public_id.'?quantity=3')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');
});

it('keeps the accepted service estimate equal to the created booking item total', function (): void {
    $service = Service::factory()->sale()->published()->create([
        'base_price_minor' => 25050,
        'base_price_currency' => 'EGP',
    ]);
    ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 5]);
    $start = now()->addDays(5)->startOfHour();
    $selection = [
        'service' => $service->public_id,
        'quantity' => 3,
        'event_starts_at' => $start->format('Y-m-d\TH:i'),
        'timezone' => 'Africa/Cairo',
    ];
    $this->get('/en/services/'.$service->public_id.'?'.http_build_query($selection))
        ->assertOk()
        ->assertViewHas('estimate', fn ($estimate): bool => $estimate->getMinorAmount()->toInt() === 75150);

    $customer = User::factory()->phoneVerified()->asCustomer()->create();
    $occasion = Occasion::factory()->create(['is_active' => true]);
    $service->category->occasions()->attach($occasion->id);
    $city = City::factory()->create(['is_active' => true]);
    $this->actingAs($customer)->post('/en/cart/setup', [
        'service_id' => $service->public_id,
        'occasion' => $occasion->code,
        'event_starts_at' => $start->format('Y-m-d\TH:i'),
        'event_ends_at' => $start->copy()->addHours(3)->format('Y-m-d\TH:i'),
        'timezone' => 'Africa/Cairo',
        'quantity' => 3,
        'address' => [
            'city_id' => $city->public_id,
            'address_line' => '21 Estimate Street',
            'recipient_name' => 'Estimate Customer',
            'recipient_phone_e164' => '+201001234567',
        ],
    ])->assertRedirect('/en/cart');

    $booking = Booking::query()->where('customer_id', $customer->id)->sole();
    $item = BookingItem::query()->where('service_id', $service->id)->sole();
    expect($item->quantity)->toBe(3)
        ->and($item->unit_price_minor)->toBe(25050)
        ->and($item->line_total_minor)->toBe(75150)
        ->and($booking->total_minor)->toBe(75150);
});

it('converts local rental windows to UTC and keeps rental quantity at one in booking setup', function (): void {
    $service = Service::factory()->rental()->published()->create();
    ServiceRentalDetail::factory()->create(['service_id' => $service->id, 'default_rental_duration_hours' => 4]);
    $customer = User::factory()->phoneVerified()->asCustomer()->create();
    $selection = ['service' => $service->public_id, 'quantity' => 1, 'event_starts_at' => '2099-01-05T10:00', 'timezone' => 'Africa/Cairo'];
    $this->get('/en/services/'.$service->public_id.'?'.http_build_query($selection))
        ->assertOk()->assertViewHas('availability', fn ($availability): bool => $availability->startsAt->toIso8601String() === '2099-01-05T08:00:00+00:00'
            && $availability->endsAt->toIso8601String() === '2099-01-05T12:00:00+00:00');
    $this->actingAs($customer)->get('/en/wizard?'.http_build_query($selection))->assertOk()
        ->assertSee('name="quantity" value="1"', false)->assertSee('value="2099-01-05T10:00"', false)
        ->assertSee('name="timezone" value="Africa/Cairo" data-browser-timezone', false)->assertSee('data-explicit-timezone', false);
    $selection['timezone'] = 'UTC';
    $this->get('/en/wizard?'.http_build_query($selection))->assertOk()->assertSee('name="timezone" value="UTC" data-browser-timezone', false);
    $this->get('/en/wizard?'.http_build_query(['service' => $service->public_id, 'timezone' => ['invalid'], 'quantity' => ['invalid']]))
        ->assertOk();
});
