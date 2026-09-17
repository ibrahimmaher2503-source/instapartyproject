<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Contracts\OtpGatewayInterface;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\VendorBusinessHoursPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorCoverageAreasPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorDocumentsPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorPhoneVerificationPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorProfilePage;
use Filament\Facades\Filament;
use Livewire\Livewire;

function vendorPhoneVerificationUser(?bool $emailVerified = true): User
{
    $profile = VendorProfile::factory()->create();
    $user = $profile->user;

    $user->forceFill([
        'email_verified_at' => $emailVerified ? now() : null,
        'phone_verified_at' => null,
    ])->save();

    return $user->fresh();
}

it('keeps the phone page behind the existing vendor email verification gate', function (): void {
    $user = vendorPhoneVerificationUser(emailVerified: false);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));

    $this->actingAs($user, 'vendor')
        ->get(VendorPhoneVerificationPage::getUrl(panel: 'vendor'))
        ->assertRedirect(Filament::getEmailVerificationPromptUrl());
});

it('sends and verifies the authenticated vendor phone through the existing OTP actions', function (): void {
    $user = vendorPhoneVerificationUser();
    $gateway = new class implements OtpGatewayInterface
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
    $this->app->instance(OtpGatewayInterface::class, $gateway);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($user, 'vendor');

    $this->get(VendorPhoneVerificationPage::getUrl(panel: 'vendor'))
        ->assertOk()
        ->assertSee(__('vendor-portal.phone_verification.title'));

    Livewire::test(VendorPhoneVerificationPage::class)
        ->assertSee(__('vendor-portal.phone_verification.title'))
        ->call('sendCode')
        ->fillForm(['code' => '123456'])
        ->call('verifyPhone')
        ->assertHasNoFormErrors();

    expect($gateway->sendCalls)->toBe(1)
        ->and($user->fresh()->phone_verified_at)->not->toBeNull();
});

it('denies the phone page to non-vendor or profile-less vendor identities', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $profileLessVendor = User::factory()->asVendor()->create();
    Filament::setCurrentPanel(Filament::getPanel('vendor'));

    $this->actingAs($customer, 'vendor')
        ->get(VendorPhoneVerificationPage::getUrl(panel: 'vendor'))
        ->assertForbidden();

    $this->actingAs($profileLessVendor, 'vendor')
        ->get(VendorPhoneVerificationPage::getUrl(panel: 'vendor'))
        ->assertForbidden();
});

it('keeps the existing profile completion pages reachable after email verification', function (): void {
    $user = vendorPhoneVerificationUser();
    Filament::setCurrentPanel(Filament::getPanel('vendor'));

    $this->actingAs($user, 'vendor');

    foreach ([
        VendorProfilePage::class,
        VendorDocumentsPage::class,
        VendorBusinessHoursPage::class,
        VendorCoverageAreasPage::class,
    ] as $page) {
        $this->get($page::getUrl(panel: 'vendor'))->assertOk();
    }
});
