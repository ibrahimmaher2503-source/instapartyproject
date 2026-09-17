<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ConfirmedState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\UnpaidState;
use App\Modules\Catalog\Application\Actions\CreateSaleServiceAction;
use App\Modules\Catalog\Application\Actions\PublishServiceAction;
use App\Modules\Catalog\Application\Actions\SubmitServiceForReviewAction;
use App\Modules\Catalog\Application\DTOs\CreateSaleServiceDTO;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ReservationStatus;
use App\Modules\Catalog\Domain\Events\SaleServiceCreated;
use App\Modules\Catalog\Domain\Events\ServicePublished;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\PendingState;
use App\Modules\Subscriptions\Application\DTOs\PolicyDecisionDto;
use App\Modules\Subscriptions\Domain\Contracts\SubscriptionPolicyContract;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\Fakes\SuccessfulPaymentGateway;

it('completes the approved sale service purchase chain with idempotent payment', function (): void {
    Event::fake([SaleServiceCreated::class, ServicePublished::class]);
    Storage::fake('public');

    app()->instance(PaymentGateway::class, new SuccessfulPaymentGateway);
    app()->instance(SubscriptionPolicyContract::class, new class implements SubscriptionPolicyContract
    {
        public function canCreateService(int $vendorProfileId, ProductType $type): PolicyDecisionDto
        {
            return PolicyDecisionDto::allow();
        }

        public function canFeature(int $vendorProfileId): PolicyDecisionDto
        {
            return PolicyDecisionDto::allow();
        }

        public function canImportExcel(int $vendorProfileId): PolicyDecisionDto
        {
            return PolicyDecisionDto::allow();
        }

        public function featuredCap(int $vendorProfileId): int
        {
            return 0;
        }

        public function maxActiveServices(int $vendorProfileId): ?int
        {
            return null;
        }

        public function commissionDiscountBps(int $vendorProfileId): int
        {
            return 0;
        }

        public function currentPlanCode(int $vendorProfileId): string
        {
            return 'testing';
        }
    });

    app()->instance(OtpGatewayInterface::class, new class implements OtpGatewayInterface
    {
        public function send(string $phoneE164, string $code): void {}

        public function verify(string $phoneE164, string $code): bool
        {
            return $code === '123456';
        }
    });

    $vendorUser = User::factory()->asVendor()->phoneVerified()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $vendorUser->id]);
    VendorApprovedProductType::factory()->forType(ProductType::Sale)->create([
        'vendor_profile_id' => $vendor->id,
    ]);

    $admin = User::factory()->asAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('publish_sale_service', 'web'));

    $category = Category::factory()->create([
        'allowed_product_types' => [ProductType::Sale->value],
    ]);
    $priceMinor = 25000;
    $service = app(CreateSaleServiceAction::class)->execute(new CreateSaleServiceDTO(
        vendorProfileId: $vendor->id,
        categoryId: $category->id,
        name: ['en' => 'Full Journey Sale Service', 'ar' => 'خدمة بيع رحلة كاملة'],
        shortDescription: ['en' => 'A complete purchase chain fixture.', 'ar' => 'بيانات اختبار لمسار شراء كامل.'],
        basePriceMinor: $priceMinor,
        isPerishable: false,
        isMadeToOrder: false,
        leadTimeHours: null,
        stockQuantity: 3,
        customizationFields: null,
    ));

    $service->addMedia(UploadedFile::fake()->image('full-journey.jpg'))->toMediaCollection('gallery');
    $service = app(SubmitServiceForReviewAction::class)->execute($service, $vendor);
    expect($service->status->getValue())->toBe('pending_review');

    $this->actingAs($admin);
    $service = app(PublishServiceAction::class)->execute($service, $admin);
    expect($service->status->getValue())->toBe('published');
    auth()->guard('web')->logout();

    $customerEmail = 'full-purchase-'.Str::lower(Str::random(10)).'@example.test';
    $customerPhone = '+2011'.fake()->unique()->numerify('########');

    $this->post('/en/auth/register', [
        'name' => 'Full Journey Customer',
        'phone_e164' => $customerPhone,
        'email' => $customerEmail,
        'password' => 'CustomerUat123!',
        'password_confirmation' => 'CustomerUat123!',
        'preferred_locale' => 'en',
        'accepted_terms' => '1',
    ])->assertRedirect('/en/auth/verify');

    $this->post('/en/auth/verify', ['code' => '123456'])
        ->assertRedirect('/en');

    $customer = User::query()->where('email', $customerEmail)->firstOrFail();
    expect($customer->phone_verified_at)->not->toBeNull();
    $this->assertAuthenticated('web');

    $occasion = Occasion::factory()->create([
        'code' => 'full-purchase-'.Str::lower(Str::random(8)),
        'is_active' => true,
    ]);
    $city = City::factory()->create([
        'name' => ['en' => 'Full Journey City', 'ar' => 'مدينة الرحلة الكاملة'],
        'is_active' => true,
    ]);
    $startsAt = now()->addDays(2)->startOfHour();
    $endsAt = $startsAt->copy()->addHours(2);

    $this->get('/en/services/'.$service->public_id)
        ->assertOk()
        ->assertSeeText('Full Journey Sale Service');

    $this->get('/en/wizard?service='.$service->public_id)
        ->assertOk()
        ->assertSee('data-planner-governorate', false)
        ->assertSee('action="'.route('storefront.cart.setup').'"', false);

    $this->post('/en/cart/setup', [
        'service_id' => $service->public_id,
        'occasion' => $occasion->code,
        'event_starts_at' => $startsAt->toDateTimeString(),
        'event_ends_at' => $endsAt->toDateTimeString(),
        'guest_count' => 2,
        'celebrant_name' => 'Full Journey Child',
        'address' => [
            'city_id' => $city->public_id,
            'address_line' => '25 Full Journey Street',
            'recipient_name' => 'Full Journey Customer',
            'recipient_phone_e164' => $customerPhone,
        ],
        'quantity' => 1,
    ])->assertRedirect('/en/cart');

    $booking = Booking::query()
        ->where('customer_id', $customer->id)
        ->where('lifecycle_status', 'draft')
        ->latest('id')
        ->firstOrFail();

    $this->postJson('/api/v1/customer/bookings/'.$booking->public_id.'/checkout-review', [], [
        'Accept' => 'application/json',
        'Accept-Language' => 'en',
    ])->assertOk()
        ->assertJsonPath('data.ready_to_submit', true)
        ->assertJsonPath('data.total_minor', $priceMinor)
        ->assertJsonPath('data.currency', 'EGP');

    $this->post('/en/cart/'.$booking->public_id.'/submit')
        ->assertRedirect('/en/cart');

    $booking->refresh();
    expect($booking->lifecycle_status)->toBeInstanceOf(VendorReviewState::class)
        ->and($booking->payment_status)->toBeInstanceOf(UnpaidState::class);

    $bookingVendor = BookingVendor::query()
        ->where('booking_id', $booking->id)
        ->where('vendor_profile_id', $vendor->id)
        ->firstOrFail();

    Sanctum::actingAs($vendorUser);
    $this->withHeaders([
        'Accept' => 'application/json',
        'Accept-Language' => 'en',
    ])->postJson('/api/v1/vendor/booking-vendors/'.$bookingVendor->public_id.'/accept', [])
        ->assertOk()
        ->assertJsonPath('data.public_id', $bookingVendor->public_id)
        ->assertJsonPath('data.sub_status', 'accepted');

    $booking->refresh();
    expect($booking->lifecycle_status)->toBeInstanceOf(ConfirmedState::class);

    $reservation = ServiceInventoryReservation::query()
        ->where('service_id', $service->id)
        ->firstOrFail();
    expect($reservation->status)->toBe(ReservationStatus::Confirmed)
        ->and($reservation->quantity)->toBe(1);

    expect(app(PaymentGateway::class))->toBeInstanceOf(SuccessfulPaymentGateway::class);
    Sanctum::actingAs($customer);
    $paymentHeaders = [
        'Accept' => 'application/json',
        'Accept-Language' => 'en',
        'Idempotency-Key' => 'full-purchase-payment-0001',
    ];

    $firstPayment = $this->withHeaders($paymentHeaders)
        ->postJson('/api/v1/customer/bookings/'.$booking->public_id.'/payments', ['method' => 'card'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.redirect_url', 'https://testing.invalid/payments/'.$booking->public_id)
        ->assertJsonPath('meta.locale', 'en')
        ->assertJsonPath('meta.direction', 'ltr');

    $secondPayment = $this->withHeaders($paymentHeaders)
        ->postJson('/api/v1/customer/bookings/'.$booking->public_id.'/payments', ['method' => 'card'])
        ->assertCreated();

    $payment = Payment::query()->where('booking_id', $booking->id)->firstOrFail();
    $booking->refresh();

    expect($secondPayment->json('data.payment_public_id'))
        ->toBe($firstPayment->json('data.payment_public_id'))
        ->and(Payment::query()->where('booking_id', $booking->id)->count())->toBe(1)
        ->and($payment->method->value)->toBe('card')
        ->and($payment->status->getValue())->toBe(PendingState::$name)
        ->and($payment->amount_minor)->toBe($priceMinor)
        ->and($payment->amount_currency)->toBe('EGP')
        ->and($payment->gateway_ref)->toBe('test-'.$booking->public_id)
        ->and($booking->payment_status)->toBeInstanceOf(UnpaidState::class);
});
