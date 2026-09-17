<?php

declare(strict_types=1);

use App\Modules\Booking\Database\Factories\BookingFactory;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Route;

it('uses the localized cart as the storefront purchase entry point', function (): void {
    $customer = User::factory()->asCustomer()->create();

    $this->actingAs($customer)
        ->get('/en/cart')
        ->assertOk();

    $this->get('/ar/cart')->assertOk();

    expect(Route::has('storefront.cart'))->toBeTrue()
        ->and(Route::has('storefront.cart.setup'))->toBeTrue()
        ->and(Route::has('storefront.cart.submit'))->toBeTrue()
        ->and(Route::has('storefront.checkout'))->toBeFalse();

    $this->get('/en/checkout')->assertNotFound();
});

it('serves a published service before the customer enters the cart flow', function (): void {
    $service = Service::factory()->sale()->published()->create([
        'name' => ['en' => 'Published test service', 'ar' => 'خدمة اختبار منشورة'],
    ]);

    $this->get('/en/services/'.$service->public_id)
        ->assertOk()
        ->assertSeeText('Published test service');
});

it('renders only the attributes supported by each service type', function (): void {
    $rental = (object) [
        'product_type' => ProductType::Rental,
        'rentalDetail' => (object) [
            'default_rental_duration_hours' => 4,
            'setup_time_minutes' => 45,
            'teardown_time_minutes' => 0,
            'minimum_space_sqm' => null,
            'requires_electricity' => true,
            'requires_outdoor_space' => false,
            'security_deposit_minor' => 0,
            'security_deposit_currency' => 'EGP',
        ],
    ];
    $rentalHtml = Blade::render('<x-storefront.service-attributes :service="$service" />', ['service' => $rental]);

    expect($rentalHtml)
        ->toContain('Rental duration', '4 hours', 'Requires electricity')
        ->not->toContain('Delivery method');

    $digital = (object) [
        'product_type' => ProductType::Digital,
        'digitalDetail' => (object) [
            'delivery_method' => 'email',
            'has_expiry' => true,
            'expiry_days_after_purchase' => 7,
        ],
    ];
    $digitalHtml = Blade::render('<x-storefront.service-attributes :service="$service" />', ['service' => $digital]);

    expect($digitalHtml)
        ->toContain('Delivery method', '7 days after purchase')
        ->not->toContain('Setup time');
});

it('renders the wizard payload for the cart setup contract', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $service = Service::factory()->sale()->published()->create();

    $this->actingAs($customer)
        ->get('/en/wizard?service='.$service->public_id)
        ->assertOk()
        ->assertSee('action="'.route('storefront.cart.setup').'"', false)
        ->assertSee('name="service_id"', false)
        ->assertSee('name="address[city_id]"', false)
        ->assertSee('name="address[address_line]"', false)
        ->assertSee('name="address[recipient_phone_e164]"', false)
        ->assertSee('<input type="hidden" name="_token"', false);
});

it('requires checkout review while the booking is still a customer draft', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = BookingFactory::new()->draft()->create(['customer_id' => $customer->id]);

    $this->actingAs($customer)
        ->postJson('/api/v1/customer/bookings/'.$booking->public_id.'/checkout-review', [], [
            'Accept' => 'application/json',
            'Accept-Language' => 'en',
        ])
        ->assertOk()
        ->assertJsonPath('data.checks.is_draft', true)
        ->assertJsonStructure(['data', 'meta', 'errors']);

    $booking->update(['lifecycle_status' => VendorReviewState::class]);

    $this->actingAs($customer)
        ->postJson('/api/v1/customer/bookings/'.$booking->public_id.'/checkout-review', [], [
            'Accept' => 'application/json',
            'Accept-Language' => 'en',
        ])
        ->assertOk()
        ->assertJsonPath('data.checks.is_draft', false);
});

it('keeps payment initiation on the existing customer booking API contract', function (): void {
    expect(Route::has('payments.initiate'))->toBeTrue();

    $route = collect(Route::getRoutes())
        ->first(fn ($route): bool => $route->getName() === 'payments.initiate');

    $middleware = $route?->gatherMiddleware() ?? [];

    expect($route?->uri())->toBe('api/v1/customer/bookings/{bookingPublicId}/payments')
        ->and($middleware)->toContain('auth:sanctum')
        ->and($middleware)->toContain('role:customer')
        ->and($middleware)->toContain('ensure.account.active')
        ->and($middleware)->toContain('idempotency');
});
