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
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Actions\ApproveVendorDocumentAction;
use App\Modules\Identity\Application\Actions\ApproveVendorForTypeAction;
use App\Modules\Identity\Application\Actions\ApproveVendorProfileAction;
use App\Modules\Identity\Application\Actions\UploadVendorDocumentAction;
use App\Modules\Identity\Application\Services\VendorApprovalEligibilityService;
use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;
use App\Modules\Identity\Domain\Enums\DocumentStatus;
use App\Modules\Identity\Domain\Enums\DocumentType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorBusinessHour;
use App\Modules\Identity\Domain\Models\VendorCoverageArea;
use App\Modules\Identity\Domain\Models\VendorDocument;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Identity\Filament\Vendor\Pages\VendorBusinessHoursPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorCoverageAreasPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorDocumentsPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorPhoneVerificationPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorProfilePage;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\PendingState;
use App\Modules\Subscriptions\Domain\Models\SubscriptionPlan;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Fakes\SuccessfulPaymentGateway;

it('completes registration, approval, sale publication, booking, and payment for one marketplace pair', function (): void {
    Mail::fake();
    Notification::fake();
    Queue::fake();
    Storage::fake('local');
    Storage::fake('public');
    Storage::fake('s3-public');

    app()->instance(PaymentGateway::class, new SuccessfulPaymentGateway);
    $otp = new class implements OtpGatewayInterface
    {
        public int $sendCalls = 0;

        public function send(string $phoneE164, string $code): void
        {
            $this->sendCalls++;
        }

        public function verify(string $phoneE164, string $code): bool
        {
            return $code === '123456';
        }
    };
    app()->instance(OtpGatewayInterface::class, $otp);

    // VendorRegistered auto-enrols the real free plan used by the real policy.
    SubscriptionPlan::factory()->free()->create();
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);
    $vendorEmail = 'market-vendor-'.Str::lower(Str::random(8)).'@example.test';
    $vendorPhone = '+2011'.fake()->unique()->numerify('########');

    $registration = $this->postJson('/api/v1/register/vendor', [
        'name' => 'Marketplace Vendor',
        'email' => $vendorEmail,
        'phone_e164' => $vendorPhone,
        'password' => 'SafePassword123!',
        'password_confirmation' => 'SafePassword123!',
        'business_name' => ['en' => 'Marketplace Events', 'ar' => 'فعاليات السوق'],
        'business_type' => 'individual',
        'primary_governorate_id' => $governorate->id,
        'primary_city_id' => $city->id,
        'preferred_locale' => 'en',
    ])->assertCreated();

    $vendorUser = User::query()->where('email', $vendorEmail)->firstOrFail();
    $vendor = VendorProfile::query()->where('user_id', $vendorUser->id)->firstOrFail();
    expect($registration->json('data.id'))->toBe($vendor->public_id)
        ->and($vendorUser->public_id)->not->toBeEmpty()
        ->and($vendor->approval_status->getValue())->toBe('pending');

    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $emailVerificationUrl = Filament::getPanel('vendor')->getVerifyEmailUrl($vendorUser);
    $this->actingAs($vendorUser, 'vendor')->get($emailVerificationUrl)->assertRedirect();
    $vendorUser = $vendorUser->fresh();
    expect($vendorUser->email_verified_at)->not->toBeNull();

    $this->actingAs($vendorUser, 'vendor');
    Livewire::test(VendorPhoneVerificationPage::class)
        ->call('sendCode')
        ->fillForm(['code' => '123456'])
        ->call('verifyPhone')
        ->assertHasNoFormErrors();
    $vendorUser = $vendorUser->fresh();
    expect($otp->sendCalls)->toBe(1)
        ->and($vendorUser->phone_verified_at)->not->toBeNull();

    Livewire::test(VendorProfilePage::class)
        ->fillForm([
            'business_name_en' => 'Marketplace Events',
            'business_name_ar' => 'فعاليات السوق',
            'address_line_en' => '12 Market Street',
            'address_line_ar' => '١٢ شارع السوق',
            'national_id' => '29801011234567',
            'primary_governorate_id' => $governorate->id,
            'primary_city_id' => $city->id,
            'bank_name' => 'Example Bank',
            'bank_account_holder' => 'Marketplace Vendor',
            'bank_iban' => 'EG380019000500000000263180002',
            'bank_swift' => 'EXAMPLEGXXX',
        ])
        ->call('save')
        ->assertHasNoFormErrors();
    $vendor = $vendor->fresh();
    expect($vendor->getTranslation('business_name', 'en'))->toBe('Marketplace Events')
        ->and($vendor->getTranslation('business_name', 'ar'))->toBe('فعاليات السوق')
        ->and($vendor->getRawOriginal('bank_iban'))->toBe('EG380019000500000000263180002');

    Livewire::test(VendorDocumentsPage::class)->assertActionExists('upload');
    $upload = app(UploadVendorDocumentAction::class);
    $nationalId = $upload->execute($vendor, UploadedFile::fake()->create('national-id.pdf', 40, 'application/pdf'), DocumentType::NationalId);
    $ibanProof = $upload->execute($vendor, UploadedFile::fake()->create('iban-proof.pdf', 40, 'application/pdf'), DocumentType::IbanProof);
    expect($nationalId->public_id)->not->toBeEmpty()
        ->and($ibanProof->public_id)->not->toBeEmpty()
        ->and($nationalId->status)->toBe(DocumentStatus::Pending)
        ->and($ibanProof->status)->toBe(DocumentStatus::Pending);

    config(['auth.defaults.guard' => 'web']);
    $admin = User::factory()->asAdmin()->create();
    foreach (['review_vendor_documents', 'approve_vendor_profile', 'approve_vendor_for_type', 'view_any_sale::service', 'view_sale::service', 'publish_sale_service'] as $permission) {
        $admin->givePermissionTo(Permission::findOrCreate($permission, 'web'));
    }
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $admin = $admin->fresh();
    foreach (['create', 'update', 'delete', 'publish'] as $operation) {
        Permission::findOrCreate("service.{$operation}.sale.own", 'web');
    }
    $nationalId = app(ApproveVendorDocumentAction::class)->execute($nationalId, $admin);
    $ibanProof = app(ApproveVendorDocumentAction::class)->execute($ibanProof, $admin);
    expect($nationalId->fresh()->status)->toBe(DocumentStatus::Approved)
        ->and($ibanProof->fresh()->status)->toBe(DocumentStatus::Approved)
        ->and(VendorDocument::query()->where('vendor_profile_id', $vendor->id)->count())->toBe(2);

    $this->actingAs($vendorUser, 'vendor');
    Livewire::test(VendorBusinessHoursPage::class)
        ->fillForm(['day_0_open' => true, 'day_0_opens_at' => '09:00', 'day_0_closes_at' => '22:00'])
        ->call('save')
        ->assertHasNoFormErrors();
    Livewire::test(VendorCoverageAreasPage::class)
        ->callAction('addArea', data: ['city_id' => $city->id, 'delivery_fee' => 0, 'min_order' => 0])
        ->assertHasNoActionErrors();
    $vendor = $vendor->fresh();
    expect(VendorBusinessHour::query()->where('vendor_profile_id', $vendor->id)->exists())->toBeTrue()
        ->and(VendorCoverageArea::query()->where('vendor_profile_id', $vendor->id)->where('city_id', $city->id)->exists())->toBeTrue();

    $eligibility = app(VendorApprovalEligibilityService::class)->evaluate($vendor);
    expect($eligibility->eligible)->toBeTrue()
        ->and(array_filter($eligibility->checks, static fn (bool $check): bool => ! $check))->toBe([]);
    $this->actingAs($admin, 'web');
    $vendor = app(ApproveVendorProfileAction::class)->execute($vendor, $admin);
    expect($vendor->approval_status)->toBeInstanceOf(ApprovedState::class);
    $approvedType = app(ApproveVendorForTypeAction::class)->execute($vendor, ProductType::Sale, $admin);
    expect($approvedType->product_type)->toBe(ProductType::Sale)
        ->and($vendor->fresh()->approvedTypes()->where('product_type', ProductType::Sale->value)->exists())->toBeTrue();
    $vendorUser = $vendorUser->fresh(['vendorProfile']);

    $category = Category::factory()->create(['allowed_product_types' => [ProductType::Sale->value]]);
    $priceMinor = 25000;
    $service = app(CreateSaleServiceAction::class)->execute(new CreateSaleServiceDTO(
        vendorProfileId: $vendor->id,
        categoryId: $category->id,
        name: ['en' => 'Marketplace Sale Service', 'ar' => 'خدمة بيع السوق'],
        shortDescription: ['en' => 'Connected sale fixture.', 'ar' => 'بيانات بيع مترابطة.'],
        basePriceMinor: $priceMinor,
        isPerishable: false,
        isMadeToOrder: false,
        leadTimeHours: null,
        stockQuantity: 3,
        customizationFields: null,
    ));
    $service->addMedia(UploadedFile::fake()->image('marketplace.jpg'))->toMediaCollection('gallery');
    $service = app(SubmitServiceForReviewAction::class)->execute($service, $vendor);
    $this->actingAs($admin, 'web');
    $service = app(PublishServiceAction::class)->execute($service, $admin);
    expect($service->public_id)->not->toBeEmpty()
        ->and($service->status->getValue())->toBe('published');
    auth()->guard('web')->logout();

    $customerEmail = 'market-customer-'.Str::lower(Str::random(8)).'@example.test';
    $customerPhone = '+2010'.fake()->unique()->numerify('########');
    $this->postJson('/api/v1/register/customer', [
        'name' => 'Marketplace Customer',
        'phone_e164' => $customerPhone,
        'email' => $customerEmail,
        'password' => 'CustomerUat123!',
        'password_confirmation' => 'CustomerUat123!',
        'preferred_locale' => 'en',
        'accepted_terms' => '1',
    ])->assertCreated();
    $this->postJson('/api/v1/phone/otp/send', ['phone_e164' => $customerPhone])->assertAccepted();
    $verification = $this->postJson('/api/v1/phone/verify', ['phone_e164' => $customerPhone, 'code' => '123456'])->assertOk();
    $customer = User::query()->where('email', $customerEmail)->firstOrFail();
    expect($verification->json('data.token'))->not->toBeEmpty()
        ->and($customer->phone_verified_at)->not->toBeNull()
        ->and($customer->public_id)->not->toBeEmpty();
    $this->actingAs($customer, 'web');

    $occasion = Occasion::factory()->create(['code' => 'market-'.Str::lower(Str::random(8)), 'is_active' => true]);
    $startsAt = now()->addDays(2)->startOfHour();
    $endsAt = $startsAt->copy()->addHours(2);
    $this->get('/en/services/'.$service->public_id)->assertOk()->assertSeeText('Marketplace Sale Service');
    $this->get('/en/wizard?service='.$service->public_id)
        ->assertOk()
        ->assertSee('data-planner-governorate', false)
        ->assertSee('action="'.route('storefront.cart.setup', ['locale' => 'en']).'"', false);
    $this->post('/en/cart/setup', [
        'service_id' => $service->public_id,
        'occasion' => $occasion->code,
        'event_starts_at' => $startsAt->toDateTimeString(),
        'event_ends_at' => $endsAt->toDateTimeString(),
        'guest_count' => 2,
        'celebrant_name' => 'Marketplace Child',
        'address' => [
            'city_id' => $city->public_id,
            'address_line' => '25 Market Street',
            'recipient_name' => 'Marketplace Customer',
            'recipient_phone_e164' => $customerPhone,
        ],
        'quantity' => 1,
    ])->assertRedirect('/en/cart');

    $booking = Booking::query()->where('customer_id', $customer->id)->where('lifecycle_status', 'draft')->latest('id')->firstOrFail();
    expect($booking->public_id)->not->toBeEmpty()
        ->and($booking->items()->where('service_id', $service->id)->exists())->toBeTrue();
    $this->postJson('/api/v1/customer/bookings/'.$booking->public_id.'/checkout-review', [], ['Accept' => 'application/json', 'Accept-Language' => 'en'])
        ->assertOk()
        ->assertJsonPath('data.ready_to_submit', true)
        ->assertJsonPath('data.total_minor', $priceMinor)
        ->assertJsonPath('data.currency', 'EGP');
    $this->post('/en/cart/'.$booking->public_id.'/submit')->assertRedirect('/en/cart');
    $booking->refresh();
    expect($booking->lifecycle_status)->toBeInstanceOf(VendorReviewState::class)
        ->and($booking->payment_status)->toBeInstanceOf(UnpaidState::class);

    $bookingVendor = BookingVendor::query()->where('booking_id', $booking->id)->where('vendor_profile_id', $vendor->id)->firstOrFail();
    expect($bookingVendor->public_id)->not->toBeEmpty();
    Sanctum::actingAs($vendorUser);
    $this->withHeaders(['Accept' => 'application/json', 'Accept-Language' => 'en'])
        ->postJson('/api/v1/vendor/booking-vendors/'.$bookingVendor->public_id.'/accept', [])
        ->assertOk()
        ->assertJsonPath('data.public_id', $bookingVendor->public_id)
        ->assertJsonPath('data.sub_status', 'accepted');
    $booking->refresh();
    expect($booking->lifecycle_status)->toBeInstanceOf(ConfirmedState::class);
    $reservation = ServiceInventoryReservation::query()->where('service_id', $service->id)->firstOrFail();
    expect($reservation->status)->toBe(ReservationStatus::Confirmed)->and($reservation->quantity)->toBe(1);

    expect(app(PaymentGateway::class))->toBeInstanceOf(SuccessfulPaymentGateway::class);
    Sanctum::actingAs($customer);
    $headers = ['Accept' => 'application/json', 'Accept-Language' => 'en', 'Idempotency-Key' => 'marketplace-payment-0001'];
    $first = $this->withHeaders($headers)->postJson('/api/v1/customer/bookings/'.$booking->public_id.'/payments', ['method' => 'card'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.redirect_url', 'https://testing.invalid/payments/'.$booking->public_id);
    $second = $this->withHeaders($headers)->postJson('/api/v1/customer/bookings/'.$booking->public_id.'/payments', ['method' => 'card'])->assertCreated();
    $payment = Payment::query()->where('booking_id', $booking->id)->firstOrFail();
    expect($second->json('data.payment_public_id'))->toBe($first->json('data.payment_public_id'))
        ->and(Payment::query()->where('booking_id', $booking->id)->count())->toBe(1)
        ->and($payment->method->value)->toBe('card')
        ->and($payment->status->getValue())->toBe(PendingState::$name)
        ->and($payment->amount_minor)->toBe($priceMinor)
        ->and($payment->amount_currency)->toBe('EGP')
        ->and($payment->gateway_ref)->toBe('test-'.$booking->public_id)
        ->and($booking->refresh()->payment_status)->toBeInstanceOf(UnpaidState::class);
});
