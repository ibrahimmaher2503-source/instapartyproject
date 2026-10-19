<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\DraftState;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

it('offers only state eligible booking actions to the customer in both locales', function (string $locale): void {
    $customer = User::factory()->phoneVerified()->asCustomer()->create();
    $booking = Booking::factory()->create(['customer_id' => $customer->id, 'lifecycle_status' => 'vendor_review']);
    $allocation = BookingVendor::factory()->create(['booking_id' => $booking->id, 'sub_status' => 'pending']);
    $item = BookingItem::factory()->sale()->create(['booking_vendor_id' => $allocation->id]);
    $url = "/{$locale}/account/bookings/{$booking->public_id}";
    $this->actingAs($customer)->get($url)->assertOk()
        ->assertSee('data-preview=', false)->assertDontSee('data-payment data-error=', false)
        ->assertSee(__('account.review_unavailable', [], $locale));

    $booking->update(['lifecycle_status' => 'confirmed']);
    $allocation->update(['sub_status' => 'accepted']);
    $this->get($url)->assertOk()->assertSee('data-preview=', false)->assertSee('data-payment data-error=', false);

    $booking->update(['lifecycle_status' => 'completed']);
    $allocation->update(['sub_status' => 'completed']);
    $item->update(['item_status' => 'completed']);
    $this->get($url)->assertOk()->assertDontSee('data-preview=', false)->assertDontSee('data-payment data-error=', false)
        ->assertSee(route('reviews.service.store', $item->public_id), false)
        ->assertDontSee(__('account.review_unavailable', [], $locale));

    $other = User::factory()->phoneVerified()->asCustomer()->create();
    $this->actingAs($other)->get($url)->assertNotFound();
})->with(['en', 'ar']);

it('keeps historical service snapshots while replacing an unavailable service link', function (ProductType $type, string $locale): void {
    config()->set('catalog.digital_services_enabled', true);
    $customer = User::factory()->phoneVerified()->asCustomer()->create();
    $booking = Booking::factory()->create(['customer_id' => $customer->id, 'lifecycle_status' => 'completed']);
    $allocation = BookingVendor::factory()->create(['booking_id' => $booking->id, 'sub_status' => 'completed']);
    $service = Service::factory()->published()->create(['product_type' => $type]);
    $item = BookingItem::factory()->create([
        'booking_vendor_id' => $allocation->id, 'service_id' => $service->id, 'product_type' => $type,
        'item_status' => 'completed', 'name_snapshot' => ['en' => 'Historical service', 'ar' => 'الخدمة التاريخية'],
    ]);
    $url = "/{$locale}/account/bookings/{$booking->public_id}";
    $link = url("/{$locale}/services/{$service->public_id}");
    $this->actingAs($customer)->get($url)->assertOk()->assertSee($link, false);
    $service->status->transitionTo(DraftState::class);
    $this->get($url)->assertOk()->assertDontSee($link, false)
        ->assertSee($item->name_snapshot[$locale])
        ->assertSee(__('account.service_unavailable', [], $locale));
    $service->update(['status' => 'published']);
    $service->delete();
    $this->get($url)->assertOk()->assertDontSee($link, false)->assertSee($item->name_snapshot[$locale]);
})->with(ProductType::cases())->with(['en', 'ar']);

it('uses the real web session for the existing API cancellation flow', function (): void {
    config()->set('sanctum.stateful', ['localhost']);
    expect(app('router')->getMiddlewareGroups()['api'])->toContain(EnsureFrontendRequestsAreStateful::class);
    $customer = User::factory()->phoneVerified()->asCustomer()->create([
        'email' => 'booking-actions@example.test', 'password' => 'SafePassword123!',
    ]);
    $booking = Booking::factory()->create(['customer_id' => $customer->id, 'lifecycle_status' => 'vendor_review']);
    BookingVendor::factory()->create(['booking_id' => $booking->id, 'sub_status' => 'pending']);
    $this->post('/en/auth/login', ['login' => $customer->email, 'password' => 'SafePassword123!'])->assertRedirect();
    $this->withCookie(config('session.cookie'), session()->getId());
    Auth::forgetGuards();
    $this->withHeader('Origin', 'http://localhost')
        ->getJson('/api/v1/customer/bookings/'.$booking->public_id.'/cancellation-preview')
        ->assertOk()->assertJsonPath('data.cancellable', true);
    $this->withHeader('Idempotency-Key', (string) Str::uuid())
        ->postJson('/api/v1/customer/bookings/'.$booking->public_id.'/cancel', [])
        ->assertOk();
    expect($booking->fresh()->lifecycle_status->getValue())->toBe('cancelled');
});

it('keeps the configured app origin stateful when extra Sanctum domains are configured', function (): void {
    $originalUrl = config('app.url');
    $originalStatefulDomains = config('sanctum.stateful');
    $originalDomains = getenv('SANCTUM_STATEFUL_DOMAINS');
    $hadOriginalDomains = $originalDomains !== false;
    $originalEnvValue = $_ENV['SANCTUM_STATEFUL_DOMAINS'] ?? null;
    $originalServerValue = $_SERVER['SANCTUM_STATEFUL_DOMAINS'] ?? null;

    try {
        config()->set('app.url', 'https://instaparty.test:8443');
        putenv('SANCTUM_STATEFUL_DOMAINS=extra.test,extra.test:3000');
        $_ENV['SANCTUM_STATEFUL_DOMAINS'] = 'extra.test,extra.test:3000';
        $_SERVER['SANCTUM_STATEFUL_DOMAINS'] = 'extra.test,extra.test:3000';
        $sanctumConfig = require config_path('sanctum.php');
        config()->set('sanctum.stateful', $sanctumConfig['stateful']);

        expect(config('sanctum.stateful'))->toContain('instaparty.test:8443');

        $customer = User::factory()->phoneVerified()->asCustomer()->create([
            'email' => 'booking-actions-stateful@example.test', 'password' => 'SafePassword123!',
        ]);
        $booking = Booking::factory()->create(['customer_id' => $customer->id, 'lifecycle_status' => 'vendor_review']);
        BookingVendor::factory()->create(['booking_id' => $booking->id, 'sub_status' => 'pending']);
        $this->post('/en/auth/login', ['login' => $customer->email, 'password' => 'SafePassword123!'])->assertRedirect();
        $this->withCookie(config('session.cookie'), session()->getId());
        Auth::forgetGuards();

        $this->withHeader('Origin', 'https://instaparty.test:8443')
            ->getJson('/api/v1/customer/bookings/'.$booking->public_id.'/cancellation-preview')
            ->assertOk()->assertJsonPath('data.cancellable', true);
        $this->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/customer/bookings/'.$booking->public_id.'/cancel', [])
            ->assertOk();
        expect($booking->fresh()->lifecycle_status->getValue())->toBe('cancelled');

        expect(EnsureFrontendRequestsAreStateful::fromFrontend(Request::create('/', 'GET', [], [], [], [
            'HTTP_ORIGIN' => 'https://foreign.test',
        ])))->toBeFalse();
        expect(EnsureFrontendRequestsAreStateful::fromFrontend(Request::create('/', 'GET', [], [], [], [
            'HTTP_HOST' => 'attacker.test',
        ])))->toBeFalse();
    } finally {
        config()->set('app.url', $originalUrl);
        config()->set('sanctum.stateful', $originalStatefulDomains);
        if ($hadOriginalDomains) {
            putenv('SANCTUM_STATEFUL_DOMAINS='.$originalDomains);
        } else {
            putenv('SANCTUM_STATEFUL_DOMAINS');
        }
        if ($originalEnvValue === null) {
            unset($_ENV['SANCTUM_STATEFUL_DOMAINS']);
        } else {
            $_ENV['SANCTUM_STATEFUL_DOMAINS'] = $originalEnvValue;
        }
        if ($originalServerValue === null) {
            unset($_SERVER['SANCTUM_STATEFUL_DOMAINS']);
        } else {
            $_SERVER['SANCTUM_STATEFUL_DOMAINS'] = $originalServerValue;
        }
    }
});

it('rejects unauthenticated cancellation preview requests from the app origin', function (): void {
    $this->withHeader('Origin', 'https://instaparty.test:8443')
        ->getJson('/api/v1/customer/bookings/01J00000000000000000000000/cancellation-preview')
        ->assertUnauthorized();
});

it('keeps bearer-authenticated cancellation preview available without a frontend origin', function (): void {
    $customer = User::factory()->phoneVerified()->asCustomer()->create();
    $booking = Booking::factory()->create(['customer_id' => $customer->id, 'lifecycle_status' => 'vendor_review']);
    BookingVendor::factory()->create(['booking_id' => $booking->id, 'sub_status' => 'pending']);
    $token = $customer->createToken('mobile')->plainTextToken;

    $this->withToken($token)
        ->getJson('/api/v1/customer/bookings/'.$booking->public_id.'/cancellation-preview')
        ->assertOk()->assertJsonPath('data.cancellable', true);
});
