<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAddress;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function checkoutSetupPayload(Service $service, Occasion $occasion, City $city): array
{
    return [
        'service_id' => $service->public_id,
        'occasion' => $occasion->code,
        'event_starts_at' => now()->addDays(2)->startOfHour()->toDateTimeString(),
        'event_ends_at' => now()->addDays(2)->startOfHour()->addHours(3)->toDateTimeString(),
        'guest_count' => 20,
        'celebrant_name' => 'Checkout Guest',
        'address' => [
            'city_id' => $city->public_id,
            'address_line' => '15 Checkout Street',
            'recipient_name' => 'Checkout Customer',
            'recipient_phone_e164' => '+201001234567',
        ],
        'quantity' => 1,
        'timezone' => 'Africa/Cairo',
    ];
}

it('persists one complete draft when save and continue succeeds', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $service = Service::factory()->sale()->published()->create();
    ServiceSaleDetail::factory()->create([
        'service_id' => $service->id,
        'is_made_to_order' => false,
        'lead_time_hours' => null,
        'stock_quantity' => 5,
    ]);
    $occasion = Occasion::factory()->create(['is_active' => true]);
    $service->category->occasions()->attach($occasion->id);
    $city = City::factory()->create(['is_active' => true]);

    $this->actingAs($customer)
        ->post('/en/cart/setup', checkoutSetupPayload($service, $occasion, $city))
        ->assertRedirect('/en/cart');

    expect(Booking::query()->where('customer_id', $customer->id)->count())->toBe(1)
        ->and(BookingAddress::query()->count())->toBe(1)
        ->and(BookingVendor::query()->count())->toBe(1)
        ->and(BookingItem::query()->count())->toBe(1);
});

it('rolls back the draft when adding the requested service fails', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $service = Service::factory()->sale()->published()->create();
    ServiceSaleDetail::factory()->create([
        'service_id' => $service->id,
        'is_made_to_order' => false,
        'lead_time_hours' => null,
        'stock_quantity' => 0,
    ]);
    $occasion = Occasion::factory()->create(['is_active' => true]);
    $service->category->occasions()->attach($occasion->id);
    $city = City::factory()->create(['is_active' => true]);

    $this->actingAs($customer)
        ->from('/en/wizard?service='.$service->public_id)
        ->post('/en/cart/setup', checkoutSetupPayload($service, $occasion, $city))
        ->assertRedirect('/en/wizard?service='.$service->public_id)
        ->assertSessionHasErrors('service_id');

    expect(Booking::query()->where('customer_id', $customer->id)->count())->toBe(0)
        ->and(BookingAddress::query()->count())->toBe(0)
        ->and(BookingVendor::query()->count())->toBe(0)
        ->and(BookingItem::query()->count())->toBe(0);
});
