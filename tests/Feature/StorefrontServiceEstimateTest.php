<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use App\Modules\Catalog\Domain\Models\ServiceRentalDetail;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Identity\Domain\Models\User;

it('estimates the booking unit price for each product and locale without creating a hold', function (string $type, string $locale): void {
    $service = Service::factory()->{$type}()->published()->create(['base_price_minor' => 25050, 'base_price_currency' => 'EGP']);
    if ($type === 'rental') {
        ServiceRentalDetail::factory()->create(['service_id' => $service->id, 'default_rental_duration_hours' => 4]);
    } elseif ($type === 'sale') {
        ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 10]);
    }
    $query = ['quantity' => 3, 'event_starts_at' => now()->addDays(5)->format('Y-m-d\TH:i'), 'timezone' => 'Africa/Cairo'];
    $this->get("/{$locale}/services/{$service->public_id}?".http_build_query($query))
        ->assertOk()->assertViewHas('estimate', fn ($estimate): bool => $estimate->getMinorAmount()->toInt() === 75150)
        ->assertViewHas('availability', fn ($availability): bool => $availability->available)
        ->assertSee('quantity=3', false)->assertSee('event_starts_at=', false);
    expect(ServiceInventoryReservation::query()->count())->toBe(0);
})->with(['rental', 'sale', 'digital'])->with(['en', 'ar']);

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

it('converts local rental windows to UTC and keeps the selected quantity in booking setup', function (): void {
    $service = Service::factory()->rental()->published()->create();
    ServiceRentalDetail::factory()->create(['service_id' => $service->id, 'default_rental_duration_hours' => 4]);
    $customer = User::factory()->phoneVerified()->asCustomer()->create();
    $selection = ['service' => $service->public_id, 'quantity' => 3, 'event_starts_at' => '2099-01-05T10:00', 'timezone' => 'Africa/Cairo'];
    $this->get('/en/services/'.$service->public_id.'?'.http_build_query($selection))
        ->assertOk()->assertViewHas('availability', fn ($availability): bool => $availability->startsAt->toIso8601String() === '2099-01-05T08:00:00+00:00'
            && $availability->endsAt->toIso8601String() === '2099-01-05T12:00:00+00:00');
    $this->actingAs($customer)->get('/en/wizard?'.http_build_query($selection))->assertOk()
        ->assertSee('name="quantity" value="3"', false)->assertSee('value="2099-01-05T10:00"', false)
        ->assertSee('name="timezone" value="Africa/Cairo" data-browser-timezone', false)->assertSee('data-explicit-timezone', false);
    $selection['timezone'] = 'UTC';
    $this->get('/en/wizard?'.http_build_query($selection))->assertOk()->assertSee('name="timezone" value="UTC" data-browser-timezone', false);
    $this->get('/en/wizard?'.http_build_query(['service' => $service->public_id, 'timezone' => ['invalid'], 'quantity' => ['invalid']]))
        ->assertOk();
});
