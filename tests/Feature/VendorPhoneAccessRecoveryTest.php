<?php

declare(strict_types=1);

use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingsPage;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Filament\Vendor\Pages\VendorCategoriesPage;
use App\Modules\Catalog\Filament\Vendor\Pages\VendorImportSaleServicesPage;
use App\Modules\Catalog\Filament\Vendor\Pages\VendorServicesListPage;
use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\VendorAccountPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorPhoneVerificationPage;
use App\Modules\Reviews\Filament\Vendor\Pages\VendorReviewsPage;
use App\Modules\Settlement\Filament\Vendor\Pages\VendorWalletPage;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

it('guides a phone change through verification then restores approved commerce access', function (string $locale): void {
    app()->setLocale($locale);
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    VendorApprovedProductType::factory()->forType(ProductType::Sale)->create(['vendor_profile_id' => $vendor->id]);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($user, 'vendor');
    $pages = [VendorServicesListPage::class, VendorCategoriesPage::class, VendorImportSaleServicesPage::class,
        VendorReviewsPage::class, VendorWalletPage::class, VendorBookingsPage::class];
    foreach ($pages as $page) {
        $this->get($page::getUrl(panel: 'vendor'))->assertOk();
    }
    Livewire::test(VendorAccountPage::class)->callAction('updatePhone', data: ['new_phone' => $user->phone_e164])
        ->assertNoRedirect();
    Livewire::test(VendorAccountPage::class)->callAction('updatePhone', data: ['new_phone' => '+201055556789'])
        ->assertRedirect(VendorPhoneVerificationPage::getUrl(panel: 'vendor'));
    $this->actingAs($user->fresh(), 'vendor');
    foreach ($pages as $page) {
        $this->get($page::getUrl(panel: 'vendor'))->assertForbidden();
    }
    $this->get(VendorPhoneVerificationPage::getUrl(panel: 'vendor'))->assertOk()
        ->assertSee(__('vendor-portal.phone_verification.description'));
    app()->instance(OtpGatewayInterface::class, new class implements OtpGatewayInterface
    {
        public function send(string $phoneE164, string $code): void {}

        public function verify(string $phoneE164, string $code): bool
        {
            return $code === '123456';
        }
    });
    Livewire::test(VendorPhoneVerificationPage::class)->call('sendCode')->fillForm(['code' => '123456'])
        ->call('verifyPhone')->assertHasNoFormErrors();
    $this->actingAs($user->fresh(), 'vendor');
    foreach ($pages as $page) {
        $this->get($page::getUrl(panel: 'vendor'))->assertOk();
    }
})->with(['en', 'ar']);

it('shows an actionable notification when the verification code cannot be sent', function (): void {
    $user = User::factory()->asVendor()->create(['phone_verified_at' => null]);
    VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($user, 'vendor');
    $gateway = Mockery::mock(OtpGatewayInterface::class);
    $gateway->shouldReceive('send')->once()->andThrow(ValidationException::withMessages([
        'phone_e164' => __('identity::sms.unavailable'),
    ]));
    app()->instance(OtpGatewayInterface::class, $gateway);

    Livewire::test(VendorPhoneVerificationPage::class)->call('sendCode')
        ->assertNotified(Notification::make()->title(__('identity::sms.unavailable'))->danger())
        ->assertSet('isVerified', false);
    expect($user->fresh()->phone_verified_at)->toBeNull();
});

it('shows localized throttling feedback without sending another code or verifying the phone', function (string $locale): void {
    app()->setLocale($locale);
    $user = User::factory()->asVendor()->create(['phone_verified_at' => null]);
    VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($user, 'vendor');
    $gateway = Mockery::mock(OtpGatewayInterface::class);
    $gateway->shouldReceive('send')->times(3);
    app()->instance(OtpGatewayInterface::class, $gateway);
    $page = Livewire::test(VendorPhoneVerificationPage::class);
    $page->call('sendCode')->call('sendCode')->call('sendCode');
    session()->forget('filament.notifications');
    $page->call('sendCode')
        ->assertNotified(Notification::make()->title(__('identity::identity.validation.otp_throttled'))->danger())
        ->assertSet('isVerified', false);
    expect($user->fresh()->phone_verified_at)->toBeNull();
})->with(['en', 'ar']);
