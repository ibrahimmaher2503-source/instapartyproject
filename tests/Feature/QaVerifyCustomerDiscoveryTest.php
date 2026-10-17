<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\AddItemToBookingAction;
use App\Modules\Booking\Application\DTOs\AddBookingItemDTO;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Catalog\Application\Actions\BlockServiceDatesAction;
use App\Modules\Catalog\Application\Actions\CheckServiceAvailabilityAction;
use App\Modules\Catalog\Application\Actions\HoldServiceInventoryAction;
use App\Modules\Catalog\Domain\Enums\HoldType;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Exceptions\InventoryNotAvailableException;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceAvailabilityBlock;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use Brick\Money\Money;
use Carbon\Carbon;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;

it('shows the authoritative estimate and carries selected inputs into the booking wizard', function (ProductType $type, string $locale): void {
    config(['catalog.digital_services_enabled' => true]);

    $factory = Service::factory()->published();
    $factory = match ($type) {
        ProductType::Rental => $factory->rental(),
        ProductType::Sale => $factory->sale(),
        ProductType::Digital => $factory->digital(),
    };
    $service = $factory->create([
        'base_price_minor' => 1234,
        'base_price_currency' => 'EGP',
    ]);
    $startsAt = Carbon::now('Africa/Cairo')->addDays(5)->setTime(14, 0)->format('Y-m-d\\TH:i');
    $quantity = $type === ProductType::Rental ? 1 : 3;
    $query = [
        'locale' => $locale,
        'servicePublicId' => $service->public_id,
        'event_starts_at' => $startsAt,
        'quantity' => $quantity,
        'timezone' => 'Africa/Cairo',
    ];

    if ($type === ProductType::Rental) {
        $query['rental_duration_hours'] = 36;
        $query['event_ends_at'] = Carbon::parse($startsAt, 'Africa/Cairo')->addHours(36)->format('Y-m-d\\TH:i');
    }

    $wizardQuery = [
        'locale' => $locale,
        'service' => $service->public_id,
        'event_starts_at' => $query['event_starts_at'],
        'quantity' => $quantity,
        'timezone' => 'Africa/Cairo',
    ];
    if (isset($query['event_ends_at'])) {
        $wizardQuery['event_ends_at'] = $query['event_ends_at'];
    }
    $wizardUrl = route('storefront.wizard', $wizardQuery);

    $response = $this->get(route('storefront.services.show', $query));
    $response
        ->assertOk()
        ->assertSee(__($type === ProductType::Sale
            ? 'storefront.service.estimate_amount'
            : 'storefront.service.estimate_title', [], $locale))
        ->assertSee(Money::ofMinor(1234, 'EGP')->multipliedBy((string) $quantity)->formatTo($locale))
        ->assertSee('/'.$locale.'/wizard?service='.$service->public_id, false)
        ->assertSee('event_starts_at='.rawurlencode($startsAt), false)
        ->assertSee('quantity='.$quantity, false);
    expect(preg_match_all('/data-service-estimate\s/', $response->getContent()))->toBe(1);
    $response->assertSee('name="event_starts_at" value="'.$startsAt.'"', false)
        ->assertSee('name="timezone" value="Africa/Cairo"', false);
    if ($type === ProductType::Rental) {
        $response->assertSee('name="quantity" value="1" data-service-estimate-quantity', false);
    } else {
        $response->assertSee('name="quantity" min="1" max="100" value="'.$quantity.'"', false);
    }
    if (isset($query['event_ends_at'])) {
        $response->assertSee('event_ends_at='.rawurlencode($query['event_ends_at']), false);
        $response->assertSee('name="event_ends_at" value="'.$query['event_ends_at'].'"', false);
    }

    $customer = User::factory()->asCustomer()->create();
    $this->actingAs($customer, 'web')
        ->get($wizardUrl)
        ->assertOk()
        ->assertSee('name="event_starts_at"', false)
        ->assertSee('value="'.$startsAt.'"', false)
        ->assertSee('name="quantity" value="'.$quantity.'"', false);
})->with([
    'rental in English' => [ProductType::Rental, 'en'],
    'rental in Arabic' => [ProductType::Rental, 'ar'],
    'sale in English' => [ProductType::Sale, 'en'],
    'sale in Arabic' => [ProductType::Sale, 'ar'],
    'digital in English' => [ProductType::Digital, 'en'],
    'digital in Arabic' => [ProductType::Digital, 'ar'],
]);

it('shows only active service packages and carries the selected package into booking', function (string $locale): void {
    app()->setLocale($locale);
    $customer = User::factory()->asCustomer()->create();
    $service = Service::factory()->sale()->published()->create(['base_price_minor' => 50000, 'base_price_currency' => 'EGP']);
    $service->vendor->approvedTypes()->create(['product_type' => ProductType::Sale, 'approved_at' => now()]);
    $createPackage = function (string $code, array $name, bool $active) use ($service) {
        $package = $service->packages()->create([
            'public_id' => (string) Str::ulid(),
            'code' => $code,
            'name' => $name,
            'description' => ['en' => 'Includes the essentials.', 'ar' => 'تشمل الأساسيات.'],
            'price_minor' => 65000,
            'currency' => 'EGP',
            'is_active' => $active,
        ]);
        $package->components()->create([
            'public_id' => (string) Str::ulid(),
            'name' => ['en' => 'Chair', 'ar' => 'كرسي'],
            'description' => ['en' => 'One chair', 'ar' => 'كرسي واحد'],
            'quantity' => 2,
            'sort_order' => 0,
        ]);

        return $package;
    };
    $package = $createPackage('qa-service-package-'.$locale, ['en' => 'Celebration bundle', 'ar' => 'باقة الاحتفال'], true);
    $inactive = $createPackage('qa-inactive-service-package-'.$locale, ['en' => 'Inactive bundle', 'ar' => 'باقة غير نشطة'], false);

    $this->get(route('storefront.services.show', ['locale' => $locale, 'servicePublicId' => $service->public_id]))
        ->assertOk()
        ->assertSee('data-service-packages', false)
        ->assertSee(__('storefront.service.choose_package', [], $locale))
        ->assertSee($package->getTranslation('name', $locale))
        ->assertDontSee($inactive->getTranslation('name', $locale))
        ->assertSee('package_public_id='.$package->public_id, false);

    $this->actingAs($customer)->get(route('storefront.wizard', [
        'locale' => $locale, 'service' => $service->public_id, 'package_public_id' => $package->public_id,
    ]))
        ->assertOk()
        ->assertSee('name="package_public_id" value="'.$package->public_id.'" checked', false)
        ->assertDontSee('value="'.$inactive->public_id.'"', false);
})->with(['en', 'ar']);

it('marks the estimate stale and disables the booking link until the customer refreshes it', function (): void {
    $service = Service::factory()->sale()->published()->create([
        'base_price_minor' => 1234,
        'base_price_currency' => 'EGP',
    ]);

    $this->get(route('storefront.services.show', [
        'locale' => 'en',
        'servicePublicId' => $service->public_id,
        'quantity' => 1,
    ]))
        ->assertOk()
        ->assertSee('data-estimate-quantity="1"', false)
        ->assertSee('data-service-estimate-refresh-status', false)
        ->assertSee(__('storefront.service.estimate_refresh_required'), false);
});

it('rejects rental quantities above one at request and action boundaries', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->draft()->create(['customer_id' => $customer->id]);
    $service = Service::factory()->rental()->published()->create();
    $service->category->occasions()->attach($booking->occasion_id);
    $startsAt = Carbon::now('UTC')->addDays(8)->setTime(14, 0);
    $endsAt = $startsAt->copy()->addHours(3);

    $this->actingAs($customer)
        ->post('/en/cart/setup', [
            'service_id' => $service->public_id,
            'occasion' => $booking->occasion->code,
            'event_starts_at' => $startsAt->toDateTimeString(),
            'event_ends_at' => $endsAt->toDateTimeString(),
            'quantity' => 2,
            'address' => [
                'city_id' => City::factory()->create()->public_id,
                'address_line' => 'Rental quantity test address',
                'recipient_name' => 'Rental quantity test',
                'recipient_phone_e164' => '+201000000000',
            ],
        ])
        ->assertSessionHasErrors('quantity');

    $this->actingAs($customer, 'sanctum')
        ->postJson('/api/v1/customer/bookings/'.$booking->public_id.'/items', [
            'service_id' => $service->public_id,
            'quantity' => 2,
            'effective_starts_at' => $startsAt->toIso8601String(),
            'effective_ends_at' => $endsAt->toIso8601String(),
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('quantity');

    $totalsBefore = [$booking->subtotal_minor, $booking->delivery_total_minor, $booking->total_minor];
    expect(fn () => app(AddItemToBookingAction::class)->execute(new AddBookingItemDTO(
        bookingId: $booking->id,
        customerId: $customer->id,
        serviceId: $service->id,
        quantity: 2,
        effectiveStartsAt: $startsAt,
        effectiveEndsAt: $endsAt,
        customizationData: null,
    )))->toThrow(HttpResponseException::class);

    expect($booking->items()->count())->toBe(0)
        ->and($booking->vendors()->count())->toBe(0)
        ->and($service->inventoryReservations()->count())->toBe(0)
        ->and([$booking->fresh()->subtotal_minor, $booking->fresh()->delivery_total_minor, $booking->fresh()->total_minor])->toBe($totalsBefore);
});

it('shows the persisted rental service dates in the customer cart', function (): void {
    $booking = Booking::factory()->draft()->create();
    $service = Service::factory()->rental()->published()->create();
    $service->category->occasions()->attach($booking->occasion_id);
    $startsAt = Carbon::now('UTC')->addDays(9)->setTime(13, 30);
    $endsAt = $startsAt->copy()->addHours(5);

    app(AddItemToBookingAction::class)->execute(new AddBookingItemDTO(
        bookingId: $booking->id,
        customerId: $booking->customer_id,
        serviceId: $service->id,
        quantity: 1,
        effectiveStartsAt: $startsAt,
        effectiveEndsAt: $endsAt,
        customizationData: null,
    ));

    $this->actingAs($booking->customer)
        ->get('/en/cart')
        ->assertOk()
        ->assertSee('data-rental-dates', false)
        ->assertSee($startsAt->toIso8601String(), false)
        ->assertSee($endsAt->toIso8601String(), false);
});

it('checks rental dates through the read-only availability contract without reserving inventory', function (): void {
    $service = Service::factory()->rental()->published()->create();
    $startsAt = Carbon::now('UTC')->addDays(3)->setTime(12, 0);

    $this->postJson('/api/v1/customer/services/'.$service->public_id.'/check-availability', [
        'starts_at' => $startsAt->toIso8601String(),
        'ends_at' => $startsAt->copy()->addHours(24)->toIso8601String(),
    ])
        ->assertOk()
        ->assertJsonPath('data.available', true)
        ->assertJsonPath('data.reserved_at', 'submit');

    $this->assertDatabaseMissing('service_inventory_reservations', ['service_id' => $service->id]);
});

it('treats vendor calendar blocks as rental conflicts while allowing adjacent windows', function (): void {
    $service = Service::factory()->rental()->create();
    $blockStart = Carbon::now('UTC')->addDays(4)->setTime(10, 0);
    $blockEnd = $blockStart->copy()->addHours(2);
    ServiceAvailabilityBlock::query()->create([
        'service_id' => $service->id,
        'starts_at' => $blockStart,
        'ends_at' => $blockEnd,
    ]);
    $availability = app(CheckServiceAvailabilityAction::class);

    $overlap = $availability->execute($service, $blockStart->copy()->addHour(), $blockEnd->copy()->addHour());
    $touchesStart = $availability->execute($service, $blockStart->copy()->subHour(), $blockStart);
    $touchesEnd = $availability->execute($service, $blockEnd, $blockEnd->copy()->addHour());

    expect($overlap->available)->toBeFalse()
        ->and($overlap->reasonCode)->toBe('window_unavailable')
        ->and($touchesStart->available)->toBeTrue()
        ->and($touchesEnd->available)->toBeTrue()
        ->and($service->inventoryReservations()->count())->toBe(0);
});

it('rechecks vendor blocks at the authoritative rental hold after a read-only available result', function (): void {
    $service = Service::factory()->rental()->create();
    $customer = User::factory()->asCustomer()->create();
    $startsAt = Carbon::now('UTC')->addDays(5)->setTime(14, 0);
    $endsAt = $startsAt->copy()->addHours(3);
    $probe = app(CheckServiceAvailabilityAction::class)->execute($service, $startsAt, $endsAt);

    app(BlockServiceDatesAction::class)->execute($service, $service->vendor, $startsAt->toISOString(), $endsAt->toISOString());

    expect($probe->available)->toBeTrue()
        ->and(fn () => app(HoldServiceInventoryAction::class)->execute(
            $service,
            HoldType::Cart,
            $customer->id,
            $startsAt,
            $endsAt,
        ))->toThrow(InventoryNotAvailableException::class)
        ->and($service->inventoryReservations()->count())->toBe(0);
});

it('rolls back booking items and totals when a rental becomes vendor-blocked before hold', function (): void {
    $booking = Booking::factory()->draft()->create();
    $service = Service::factory()->rental()->published()->create();
    $service->category->occasions()->attach($booking->occasion_id);
    $startsAt = Carbon::now('UTC')->addDays(6)->setTime(14, 0);
    $endsAt = $startsAt->copy()->addHours(3);

    $this->postJson('/api/v1/customer/services/'.$service->public_id.'/check-availability', [
        'starts_at' => $startsAt->toIso8601String(),
        'ends_at' => $endsAt->toIso8601String(),
    ])->assertOk()->assertJsonPath('data.available', true);

    app(BlockServiceDatesAction::class)->execute($service, $service->vendor, $startsAt->toISOString(), $endsAt->toISOString());
    $totalsBefore = [$booking->subtotal_minor, $booking->delivery_total_minor, $booking->total_minor];

    expect(fn () => app(AddItemToBookingAction::class)->execute(new AddBookingItemDTO(
        bookingId: $booking->id,
        customerId: $booking->customer_id,
        serviceId: $service->id,
        quantity: 1,
        effectiveStartsAt: $startsAt,
        effectiveEndsAt: $endsAt,
        customizationData: null,
    )))->toThrow(HttpResponseException::class);

    expect($service->inventoryReservations()->count())->toBe(0)
        ->and($booking->items()->count())->toBe(0)
        ->and($booking->vendors()->count())->toBe(0)
        ->and([$booking->fresh()->subtotal_minor, $booking->fresh()->delivery_total_minor, $booking->fresh()->total_minor])->toBe($totalsBefore);
});
