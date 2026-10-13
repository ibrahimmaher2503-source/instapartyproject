<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\CreateBookingDraftAction;
use App\Modules\Booking\Application\DTOs\CreateBookingDraftDTO;
use App\Modules\Booking\Application\DTOs\ServiceReadDTO;
use App\Modules\Booking\Domain\Contracts\CatalogServiceReader;
use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAddress;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\DraftState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Booking\Filament\Resources\BookingResource\Pages\CreateBooking;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Discovery\Infrastructure\Repositories\ServiceRecommendationRepository;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Shared\Domain\Models\StateTransition;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;

uses(RefreshDatabase::class);

function batch07PublishedService(ProductType $type): Service
{
    $factory = match ($type) {
        ProductType::Rental => Service::factory()->rental(),
        ProductType::Sale => Service::factory()->sale(),
        ProductType::Digital => Service::factory()->digital(),
    };

    return $factory->published()->create();
}

function batch07CheckoutPayload(Service $service, Occasion $occasion, City $city): array
{
    return [
        'service_id' => $service->public_id,
        'occasion' => $occasion->code,
        'event_starts_at' => now()->addDays(2)->startOfHour()->toDateTimeString(),
        'event_ends_at' => now()->addDays(2)->startOfHour()->addHours(3)->toDateTimeString(),
        'address' => [
            'city_id' => $city->public_id,
            'address_line' => '15 Occasion Eligibility Street',
            'recipient_name' => 'Occasion Eligibility Customer',
            'recipient_phone_e164' => '+201001234567',
        ],
        'timezone' => 'Africa/Cairo',
    ];
}

it('shows only active occasions linked to the selected service', function (ProductType $type): void {
    config(['catalog.digital_services_enabled' => true]);

    $service = batch07PublishedService($type);
    $eligible = Occasion::factory()->active()->create([
        'name' => ['en' => 'Linked Occasion Option', 'ar' => 'مناسبة مرتبطة'],
    ]);
    $unrelated = Occasion::factory()->active()->create([
        'name' => ['en' => 'Unrelated Occasion Option', 'ar' => 'مناسبة غير مرتبطة'],
    ]);
    $inactive = Occasion::factory()->inactive()->create([
        'name' => ['en' => 'Inactive Occasion Option', 'ar' => 'مناسبة غير نشطة'],
    ]);

    $service->category->occasions()->attach([$eligible->id, $inactive->id]);

    $this->actingAs(User::factory()->asCustomer()->create())
        ->get('/en/wizard?service='.$service->public_id)
        ->assertOk()
        ->assertSee('Linked Occasion Option')
        ->assertDontSee('Unrelated Occasion Option')
        ->assertDontSee('Inactive Occasion Option');
})->with([
    'rental' => ProductType::Rental,
    'sale' => ProductType::Sale,
    'digital' => ProductType::Digital,
]);

it('filters admin booking services by occasion and clears a stale selection when the occasion changes', function (): void {
    config(['catalog.digital_services_enabled' => true]);

    $admin = User::factory()->superAdmin()->create();
    $firstOccasion = Occasion::factory()->active()->create();
    $secondOccasion = Occasion::factory()->active()->create();
    $firstService = batch07PublishedService(ProductType::Sale);
    $secondService = batch07PublishedService(ProductType::Rental);
    $firstService->category->occasions()->attach($firstOccasion->id);
    $secondService->category->occasions()->attach($secondOccasion->id);

    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $page = Livewire::test(CreateBooking::class)
        ->set('data.occasion_id', $firstOccasion->id)
        ->assertFormFieldExists('service_id', fn (Select $field): bool => array_keys($field->getOptions()) === [$firstService->id]);

    $page->set('data.service_id', $firstService->id)
        ->set('data.occasion_id', $secondOccasion->id)
        ->assertSet('data.service_id', null)
        ->assertFormFieldExists('service_id', fn (Select $field): bool => array_keys($field->getOptions()) === [$secondService->id]);
});

it('preflights the admin booking occasion before creating a draft', function (): void {
    config(['catalog.digital_services_enabled' => true]);
    Notification::fake();

    $admin = User::factory()->superAdmin()->create();
    $customer = User::factory()->asCustomer()->create();
    $occasion = Occasion::factory()->active()->create();
    $service = batch07PublishedService(ProductType::Sale);
    $service->category->occasions()->attach($occasion->id);
    $city = City::factory()->create(['is_active' => true]);

    $catalogReader = Mockery::mock(CatalogServiceReader::class);
    $catalogReader->shouldReceive('findPublishedById')
        ->once()
        ->with($service->id)
        ->andReturn(new ServiceReadDTO(
            id: $service->id,
            publicId: $service->public_id,
            vendorProfileId: $service->vendor_profile_id,
            categoryId: $service->category_id,
            productType: ProductType::Sale,
            nameEn: 'Admin preflight service',
            nameAr: 'خدمة تحقق الإدارة',
            basePriceMinor: $service->base_price_minor,
            basePriceCurrency: $service->base_price_currency,
            stockQuantity: 5,
        ));
    $catalogReader->shouldReceive('isServiceEligibleForOccasion')
        ->once()
        ->with($service->id, $occasion->id)
        ->andReturnFalse();
    $this->instance(CatalogServiceReader::class, $catalogReader);

    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'occasion_id' => $occasion->id,
            'event_starts_at' => now()->addDays(2)->startOfHour()->toDateTimeString(),
            'event_ends_at' => now()->addDays(2)->startOfHour()->addHours(2)->toDateTimeString(),
            'address_city_id' => $city->id,
            'address_line' => 'Admin Preflight Street',
            'recipient_name' => 'Admin Preflight Customer',
            'recipient_phone_e164' => '+201001234567',
            'service_id' => $service->id,
            'quantity' => 1,
        ])
        ->call('create')
        ->assertNotified(Notification::make()
            ->title(__('booking.create.error'))
            ->body(__('storefront.wizard.validation.service_occasion_mismatch'))
            ->danger());

    expect(Booking::query()->where('customer_id', $customer->id)->exists())->toBeFalse();
});

it('rolls back the admin booking when submit fails after adding the item and inventory hold', function (): void {
    config(['catalog.digital_services_enabled' => true]);
    Notification::fake();

    $admin = User::factory()->superAdmin()->create();
    $customer = User::factory()->asCustomer()->create();
    $occasion = Occasion::factory()->active()->create();
    $service = Service::factory()->sale()->published()->create(['base_price_minor' => 90000]);
    ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 5]);
    $service->category->occasions()->attach($occasion->id);
    $city = City::factory()->create(['is_active' => true]);
    VendorCoverageArea::factory()->create([
        'vendor_profile_id' => $service->vendor_profile_id,
        'city_id' => $city->id,
        'min_order_minor' => 100000,
        'min_order_currency' => 'EGP',
    ]);

    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'occasion_id' => $occasion->id,
            'event_starts_at' => now()->addDays(2)->startOfHour()->toDateTimeString(),
            'event_ends_at' => now()->addDays(2)->startOfHour()->addHours(2)->toDateTimeString(),
            'address_city_id' => $city->id,
            'address_line' => 'Admin Atomicity Street',
            'recipient_name' => 'Admin Atomicity Customer',
            'recipient_phone_e164' => '+201001234567',
            'service_id' => $service->id,
            'quantity' => 1,
        ])
        ->call('create');

    expect(Booking::query()->where('customer_id', $customer->id)->count())->toBe(0)
        ->and(BookingAddress::query()->count())->toBe(0)
        ->and(BookingVendor::query()->count())->toBe(0)
        ->and(BookingItem::query()->count())->toBe(0)
        ->and(StateTransition::query()->where('transitionable_type', Booking::class)->count())->toBe(0)
        ->and(ServiceInventoryReservation::query()->where('service_id', $service->id)->count())->toBe(0);
});

it('submits an admin booking whose vendor subtotal meets a nonzero coverage minimum', function (): void {
    config(['catalog.digital_services_enabled' => true]);
    Notification::fake();

    $admin = User::factory()->superAdmin()->create();
    $customer = User::factory()->asCustomer()->create();
    $occasion = Occasion::factory()->active()->create();
    $service = Service::factory()->sale()->published()->create(['base_price_minor' => 120000]);
    ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 5]);
    $service->category->occasions()->attach($occasion->id);
    $city = City::factory()->create(['is_active' => true]);
    VendorCoverageArea::factory()->create([
        'vendor_profile_id' => $service->vendor_profile_id,
        'city_id' => $city->id,
        'min_order_minor' => 100000,
        'min_order_currency' => 'EGP',
    ]);

    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    Livewire::test(CreateBooking::class)
        ->fillForm([
            'customer_id' => $customer->id,
            'occasion_id' => $occasion->id,
            'event_starts_at' => now()->addDays(2)->startOfHour()->toDateTimeString(),
            'event_ends_at' => now()->addDays(2)->startOfHour()->addHours(2)->toDateTimeString(),
            'address_city_id' => $city->id,
            'address_line' => 'Admin Minimum Order Street',
            'recipient_name' => 'Admin Minimum Order Customer',
            'recipient_phone_e164' => '+201001234568',
            'service_id' => $service->id,
            'quantity' => 1,
        ])
        ->call('create');

    $booking = Booking::query()->where('customer_id', $customer->id)->sole();
    $transitionStates = StateTransition::query()
        ->where('transitionable_type', Booking::class)
        ->where('transitionable_id', $booking->id)
        ->orderBy('id')
        ->pluck('to_state')
        ->all();

    expect($booking->lifecycle_status)->toBeInstanceOf(VendorReviewState::class)
        ->and($booking->items()->where('service_id', $service->id)->exists())->toBeTrue()
        ->and(ServiceInventoryReservation::query()->where('service_id', $service->id)->exists())->toBeTrue()
        ->and($transitionStates)->toBe([
            LifecycleStatus::Draft->value,
            LifecycleStatus::VendorReview->value,
        ]);
});

it('writes one initial draft transition during normal draft creation', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $occasion = Occasion::factory()->active()->create();
    $city = City::factory()->create(['is_active' => true]);

    $booking = app(CreateBookingDraftAction::class)->execute(new CreateBookingDraftDTO(
        customerId: $customer->id,
        occasionId: $occasion->id,
        eventStartsAt: now()->addDays(2)->startOfHour(),
        eventEndsAt: now()->addDays(2)->startOfHour()->addHours(2),
        guestCount: null,
        theme: null,
        celebrantName: null,
        celebrantDob: null,
        celebrantGender: null,
        addressCityId: $city->id,
        addressLine: 'Normal Draft Transition Street',
        addressBuilding: null,
        addressFloor: null,
        addressApartment: null,
        addressLandmark: null,
        addressLatitude: null,
        addressLongitude: null,
        recipientName: 'Normal Draft Customer',
        recipientPhoneE164: '+201001234569',
    ));

    $transitions = StateTransition::query()
        ->where('transitionable_type', Booking::class)
        ->where('transitionable_id', $booking->id)
        ->get();

    expect($transitions)->toHaveCount(1)
        ->and($transitions->sole()->from_state)->toBeNull()
        ->and($transitions->sole()->to_state)->toBe(LifecycleStatus::Draft->value)
        ->and($transitions->sole()->trigger_kind)->toBe('system')
        ->and($transitions->sole()->triggered_by)->toBeNull();
});

it('treats a malformed service array query as general planner context', function (): void {
    $this->actingAs(User::factory()->asCustomer()->create())
        ->get('/en/wizard?service%5B%5D=invalid')
        ->assertOk()
        ->assertSee('data-booking-mode="false"', false)
        ->assertDontSee('name="service_id"', false);
});

it('does not recommend services outside the selected occasion across product types', function (): void {
    config(['catalog.digital_services_enabled' => true]);

    $occasion = Occasion::factory()->active()->create();
    $eligibleIds = [];
    $ineligibleIds = [];

    foreach (ProductType::cases() as $type) {
        $eligible = batch07PublishedService($type);
        $eligible->category->occasions()->attach($occasion->id);
        $eligibleIds[] = $eligible->id;

        $ineligibleIds[] = batch07PublishedService($type)->id;
    }

    $recommendedIds = app(ServiceRecommendationRepository::class)
        ->recommend($occasion->id, null, null)
        ->modelKeys();

    foreach ($eligibleIds as $serviceId) {
        expect($recommendedIds)->toContain($serviceId);
    }

    foreach ($ineligibleIds as $serviceId) {
        expect($recommendedIds)->not->toContain($serviceId);
    }
});

it('keeps ineligible product types out of storefront search for a selected occasion', function (): void {
    config(['catalog.digital_services_enabled' => true, 'scout.driver' => 'database']);

    $occasion = Occasion::factory()->active()->create();
    $eligibleNames = [];
    $ineligibleNames = [];

    foreach (ProductType::cases() as $type) {
        $eligible = batch07PublishedService($type);
        $eligible->category->occasions()->attach($occasion->id);
        $eligible->update(['name' => ['en' => 'Eligible '.$type->value.' Service', 'ar' => 'خدمة '.$type->value.' مناسبة']]);
        $eligibleNames[] = 'Eligible '.$type->value.' Service';

        $ineligible = batch07PublishedService($type);
        $ineligible->update(['name' => ['en' => 'Unrelated '.$type->value.' Service', 'ar' => 'خدمة '.$type->value.' غير مرتبطة']]);
        $ineligibleNames[] = 'Unrelated '.$type->value.' Service';
    }

    $response = $this->get('/en/search?occasion='.$occasion->code)->assertOk();

    foreach ($eligibleNames as $name) {
        $response->assertSee($name);
    }

    foreach ($ineligibleNames as $name) {
        $response->assertDontSee($name);
    }
});

it('rejects a tampered occasion before creating a booking for every product type', function (ProductType $type, string $locale): void {
    config(['catalog.digital_services_enabled' => true]);

    $customer = User::factory()->asCustomer()->create();
    $service = batch07PublishedService($type);
    $unrelated = Occasion::factory()->active()->create();
    $city = City::factory()->create(['is_active' => true]);
    $wizardUrl = "/{$locale}/wizard?service={$service->public_id}";

    $this->actingAs($customer)
        ->from($wizardUrl)
        ->post("/{$locale}/cart/setup", batch07CheckoutPayload($service, $unrelated, $city))
        ->assertRedirect($wizardUrl)
        ->assertSessionHasErrors('service_id');

    expect(Booking::query()->where('customer_id', $customer->id)->count())->toBe(0);

    $this->get($wizardUrl)
        ->assertOk()
        ->assertSee(trans('storefront.wizard.step1.no_service_occasions', [], $locale))
        ->assertSee(trans('storefront.wizard.validation.service_occasion_mismatch', [], $locale));
})->with([
    'rental English' => [ProductType::Rental, 'en'],
    'sale English' => [ProductType::Sale, 'en'],
    'digital English' => [ProductType::Digital, 'en'],
    'rental Arabic' => [ProductType::Rental, 'ar'],
    'sale Arabic' => [ProductType::Sale, 'ar'],
    'digital Arabic' => [ProductType::Digital, 'ar'],
]);

it('rejects inactive or deleted occasions with localized feedback instead of a server error', function (string $state, string $locale): void {
    config(['catalog.digital_services_enabled' => true]);

    $customer = User::factory()->asCustomer()->create();
    $service = batch07PublishedService(ProductType::Digital);
    $occasion = Occasion::factory()->active()->create();
    $service->category->occasions()->attach($occasion->id);
    $city = City::factory()->create(['is_active' => true]);

    if ($state === 'deleted') {
        $occasion->delete();
    } else {
        $occasion->update(['is_active' => false]);
    }

    $wizardUrl = "/{$locale}/wizard?service={$service->public_id}";

    $this->actingAs($customer)
        ->from($wizardUrl)
        ->post("/{$locale}/cart/setup", batch07CheckoutPayload($service, $occasion, $city))
        ->assertRedirect($wizardUrl)
        ->assertSessionHasErrors('service_id');

    expect(Booking::query()->where('customer_id', $customer->id)->count())->toBe(0);

    $this->get($wizardUrl)
        ->assertOk()
        ->assertSee(trans('storefront.wizard.validation.service_occasion_mismatch', [], $locale));
})->with(['inactive', 'deleted'])->with(['en', 'ar']);

it('checks the existing draft occasion before adding a service from another occasion', function (): void {
    config(['catalog.digital_services_enabled' => true]);

    $customer = User::factory()->asCustomer()->create();
    $draftOccasion = Occasion::factory()->active()->create();
    $selectedOccasion = Occasion::factory()->active()->create();
    $draftService = batch07PublishedService(ProductType::Digital);
    $selectedService = batch07PublishedService(ProductType::Digital);
    $draftService->category->occasions()->attach($draftOccasion->id);
    $selectedService->category->occasions()->attach([$draftOccasion->id, $selectedOccasion->id]);
    $city = City::factory()->create(['is_active' => true]);

    $this->actingAs($customer)
        ->post('/en/cart/setup', batch07CheckoutPayload($draftService, $draftOccasion, $city))
        ->assertRedirect('/en/cart');

    $booking = Booking::query()->where('customer_id', $customer->id)->firstOrFail();
    $wizardUrl = '/en/wizard?service='.$selectedService->public_id;

    $this->actingAs($customer)
        ->from($wizardUrl)
        ->post('/en/cart/setup', batch07CheckoutPayload($selectedService, $selectedOccasion, $city))
        ->assertRedirect($wizardUrl)
        ->assertSessionHasErrors('service_id');

    $this->get($wizardUrl)
        ->assertOk()
        ->assertSee(trans('storefront.wizard.validation.draft_occasion_mismatch', [], 'en'));

    expect($booking->fresh()->occasion_id)->toBe($draftOccasion->id)
        ->and(BookingItem::query()->where('service_id', $draftService->id)->count())->toBe(1)
        ->and(BookingItem::query()->where('service_id', $selectedService->id)->count())->toBe(0);
});

it('starts a fresh occasion draft when the latest mismatched draft is empty', function (): void {
    config(['catalog.digital_services_enabled' => true]);

    $customer = User::factory()->asCustomer()->create();
    $existingOccasion = Occasion::factory()->active()->create();
    $selectedOccasion = Occasion::factory()->active()->create();
    $service = batch07PublishedService(ProductType::Digital);
    $service->category->occasions()->attach([$existingOccasion->id, $selectedOccasion->id]);
    $emptyDraft = Booking::factory()->draft()->create([
        'customer_id' => $customer->id,
        'occasion_id' => $existingOccasion->id,
    ]);
    $city = City::factory()->create(['is_active' => true]);

    $this->actingAs($customer)
        ->post('/en/cart/setup', batch07CheckoutPayload($service, $selectedOccasion, $city))
        ->assertRedirect('/en/cart');

    $newDraft = Booking::query()
        ->where('customer_id', $customer->id)
        ->where('occasion_id', $selectedOccasion->id)
        ->sole();

    expect($emptyDraft->fresh()->occasion_id)->toBe($existingOccasion->id)
        ->and($emptyDraft->items()->count())->toBe(0)
        ->and($newDraft->items()->where('service_id', $service->id)->exists())->toBeTrue()
        ->and(Booking::query()->where('customer_id', $customer->id)->count())->toBe(2);
});

it('blocks submission when an occasion link is removed after an item enters the cart', function (): void {
    config(['catalog.digital_services_enabled' => true]);

    $customer = User::factory()->asCustomer()->create();
    $service = batch07PublishedService(ProductType::Digital);
    $occasion = Occasion::factory()->active()->create();
    $service->category->occasions()->attach($occasion->id);
    $city = City::factory()->create(['is_active' => true]);

    $this->actingAs($customer)
        ->post('/en/cart/setup', batch07CheckoutPayload($service, $occasion, $city))
        ->assertRedirect('/en/cart');

    $booking = Booking::query()->where('customer_id', $customer->id)->firstOrFail();
    $service->category->occasions()->detach($occasion->id);

    $this->actingAs($customer)
        ->post('/en/cart/'.$booking->public_id.'/submit')
        ->assertRedirect('/en/cart')
        ->assertSessionHasErrors('booking');

    $this->get('/en/cart')
        ->assertOk()
        ->assertSee(trans('storefront.wizard.validation.service_occasion_mismatch', [], 'en'));

    expect($booking->fresh()->lifecycle_status)->toBeInstanceOf(DraftState::class);
});
