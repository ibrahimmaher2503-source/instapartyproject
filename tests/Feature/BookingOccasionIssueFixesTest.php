<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\AdminDecidePendingBookingVendorAction;
use App\Modules\Booking\Database\Factories\BookingAddressFactory;
use App\Modules\Booking\Database\Factories\BookingFactory;
use App\Modules\Booking\Database\Factories\BookingVendorFactory;
use App\Modules\Booking\Domain\Enums\AdminVendorDecision;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Exceptions\ResponseDeadlineExpiredException;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingAdminIntervention;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Resources\BookingResource\Pages\ViewBooking;
use App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers\BookingVendorsRelationManager;
use App\Modules\Booking\Filament\Resources\BookingResource\RelationManagers\PaymentsRelationManager;
use App\Modules\Catalog\Application\Actions\DeleteOccasionAction;
use App\Modules\Catalog\Application\Actions\ListPublicOccasionsAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource\Pages\CreateVendorSaleService;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Database\Factories\PaymentFactory;
use App\Modules\Payments\Domain\States\PaymentStatus\AuthorizedState;
use App\Modules\Payments\Domain\States\PaymentStatus\CapturedState;
use App\Modules\Subscriptions\Application\DTOs\PolicyDecisionDto;
use App\Modules\Subscriptions\Domain\Contracts\SubscriptionPolicyContract;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

it('retains existing booking references when an in-use occasion is soft deleted', function (): void {
    $occasion = Occasion::factory()->active()->create();
    $booking = Booking::factory()->create(['occasion_id' => $occasion->id]);

    app(DeleteOccasionAction::class)->execute($occasion);

    expect($booking->fresh()->occasion_id)->toBe($occasion->id)
        ->and(Occasion::withTrashed()->findOrFail($occasion->id)->trashed())->toBeTrue()
        ->and(app(ListPublicOccasionsAction::class)->execute()->pluck('id'))->not->toContain($occasion->id);
});

it('shows only active linked occasions in the vendor service form guidance', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $profile = VendorProfile::factory()->approved()->create();
    VendorApprovedProductType::factory()->forType(ProductType::Sale)->create(['vendor_profile_id' => $profile->id]);
    $profile->user->givePermissionTo(Permission::findOrCreate('service.update.sale.own', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($profile->user, 'vendor');
    $root = Category::factory()->create(['parent_id' => null, 'allowed_product_types' => ['sale']]);
    $child = Category::factory()->create(['parent_id' => $root->id, 'allowed_product_types' => ['sale']]);
    $active = Occasion::factory()->active()->create(['name' => ['en' => 'Active party', 'ar' => 'مناسبة نشطة']]);
    $inactive = Occasion::factory()->create(['is_active' => false, 'name' => ['en' => 'Inactive party', 'ar' => 'مناسبة غير نشطة']]);
    $child->occasions()->attach([$active->id, $inactive->id]);
    $policy = Mockery::mock(SubscriptionPolicyContract::class);
    $policy->shouldReceive('canCreateService')->andReturn(PolicyDecisionDto::allow());
    app()->instance(SubscriptionPolicyContract::class, $policy);

    Livewire::test(CreateVendorSaleService::class)
        ->set('data.main_category_id', $root->id)
        ->set('data.category_id', $child->id)
        ->assertSee('Active party')
        ->assertDontSee('Inactive party');
});

it('rejects a vendor request through the API and advances the booking lifecycle', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $vendorUser = User::factory()->phoneVerified()->asVendor()->create();
    $profile = VendorProfile::factory()->approved()->create(['user_id' => $vendorUser->id]);
    $booking = BookingFactory::new()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => 'vendor_review',
    ]);
    $allocation = BookingVendorFactory::new()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $profile->id,
        'response_deadline' => now()->addHour(),
    ]);

    Sanctum::actingAs($vendorUser);
    $this->withHeaders(['Accept-Language' => 'en'])
        ->postJson('/api/v1/vendor/booking-vendors/'.$allocation->public_id.'/reject', [
            'rejection_reason' => [
                'en' => 'The requested date is unavailable.',
                'ar' => 'التاريخ المطلوب غير متاح.',
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.public_id', $allocation->public_id)
        ->assertJsonPath('data.sub_status', 'rejected');

    expect($booking->fresh()->lifecycle_status->getValue())->toBe('cancelled')
        ->and($allocation->fresh()->rejection_reason['en'])->toBe('The requested date is unavailable.');
});

it('lets an authorized admin make an auditable pending vendor decision without changing amounts', function (AdminVendorDecision $decision): void {
    $booking = Booking::factory()->create([
        'lifecycle_status' => 'vendor_review',
        'total_minor' => 45678,
        'total_currency' => 'EGP',
    ]);
    $vendor = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'sub_status' => VendorSubStatus::Pending,
        'response_deadline' => CarbonImmutable::now('UTC')->addHour(),
    ]);
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('booking.intervene.decide_vendor_request', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($admin);

    $intervention = app(AdminDecidePendingBookingVendorAction::class)->execute(
        $vendor,
        $decision,
        'Admin verified the vendor response with the customer request.',
    );

    expect($vendor->fresh()->sub_status)->toBe($decision === AdminVendorDecision::Accept ? VendorSubStatus::Accepted : VendorSubStatus::Rejected)
        ->and($booking->fresh()->total_minor)->toBe(45678)
        ->and($intervention->admin_id)->toBe($admin->id)
        ->and($intervention->after_state['acted_for_vendor_profile_id'])->toBe($vendor->vendor_profile_id)
        ->and(DB::table('audit_logs')->where('action', 'booking.vendor_decision')->where('user_id', $admin->id)->exists())->toBeTrue();
})->with([AdminVendorDecision::Accept, AdminVendorDecision::Reject]);

it('hides admin decisions after the vendor deadline and still rejects stale submissions', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    $admin->givePermissionTo(Permission::findOrCreate('booking.intervene.decide_vendor_request', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    $this->actingAs($admin);
    $booking = Booking::factory()->create(['lifecycle_status' => 'vendor_review']);
    $vendor = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'sub_status' => VendorSubStatus::Pending,
        'response_deadline' => now()->subSecond(),
    ]);

    Livewire::test(BookingVendorsRelationManager::class, [
        'ownerRecord' => $booking,
        'pageClass' => ViewBooking::class,
    ])
        ->assertTableActionHidden('admin_accept_vendor', $vendor)
        ->assertTableActionHidden('admin_reject_vendor', $vendor);

    expect(fn () => app(AdminDecidePendingBookingVendorAction::class)->execute(
        $vendor,
        AdminVendorDecision::Accept,
        'Admin verified the vendor response with the customer request.',
    ))->toThrow(ResponseDeadlineExpiredException::class)
        ->and(BookingAdminIntervention::query()->count())->toBe(0);
});

it('saves the admin transportation action through the booking form and recalculates totals', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);
    $governorate = Governorate::factory()->create(['transport_fee_minor' => 2500]);
    $city = City::factory()->create(['governorate_id' => $governorate->id]);
    $booking = Booking::factory()->create();
    BookingAddressFactory::new()->create(['booking_id' => $booking->id, 'city_id' => $city->id]);
    BookingVendorFactory::new()->create([
        'booking_id' => $booking->id, 'subtotal_minor' => 10000, 'delivery_fee_minor' => 500,
    ]);

    Livewire::test(ViewBooking::class, ['record' => $booking->public_id])
        ->callAction('addTransportationLine', data: ['amount' => '10.00', 'source' => 'QA invoice line', 'source_reference' => 'QA-169'])
        ->assertHasNoActionErrors();

    expect($booking->fresh()->total_minor)->toBe(14000)
        ->and(DB::table('booking_adjustments')->where('booking_id', $booking->id)->where('source_reference', 'QA-169')->exists())->toBeTrue();
});

it('shows manual capture context for authorized payments and hides it after capture', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->superAdmin()->create();
    $customer = User::factory()->asCustomer()->create(['name' => 'Capture customer']);
    $this->actingAs($admin);
    $profile = VendorProfile::factory()->approved()->create([
        'business_name' => ['en' => 'Capture vendor', 'ar' => 'مورد التحصيل'],
    ]);
    $booking = Booking::factory()->create(['customer_id' => $customer->id]);
    BookingVendorFactory::new()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $profile->id,
    ]);
    $authorized = PaymentFactory::new()->create([
        'booking_id' => $booking->id,
        'user_id' => $customer->id,
        'amount_minor' => 32100,
        'amount_currency' => 'EGP',
        'gateway_ref' => 'QA-CAPTURE-79',
        'status' => AuthorizedState::class,
    ]);

    $authorizedComponent = Livewire::test(PaymentsRelationManager::class, [
        'ownerRecord' => $booking,
        'pageClass' => ViewBooking::class,
    ])->assertTableActionVisible('capture', $authorized);

    $description = $authorizedComponent->instance()->getTable()->getAction('capture')
        ->record($authorized)
        ->getModalDescription();

    expect($description)->toContain('QA-CAPTURE-79')
        ->toContain('Capture customer')
        ->toContain('Capture vendor')
        ->toContain('does not contact the payment gateway');

    $captured = PaymentFactory::new()->create([
        'booking_id' => $booking->id,
        'user_id' => $customer->id,
        'status' => CapturedState::class,
    ]);

    Livewire::test(PaymentsRelationManager::class, [
        'ownerRecord' => $booking,
        'pageClass' => ViewBooking::class,
    ])
        ->assertTableActionHidden('capture', $captured)
        ->assertTableActionHidden('void', $captured);
});
