<?php

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\RegisterVendorPage;
use Filament\Facades\Filament;
use Livewire\Livewire;

function privacyRegistrationForm(string $email): array
{
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);

    return [
        'name' => 'Vendor owner', 'email' => $email, 'phone_e164' => '+201000000654',
        'password' => 'RegistrationOwner123!', 'passwordConfirmation' => 'RegistrationOwner123!',
        'business_name_en' => 'Vendor business', 'business_name_ar' => 'نشاط المورد',
        'business_type' => 'individual', 'primary_governorate_id' => $governorate->id, 'primary_city_id' => $city->id,
    ];
}

it('does not expose existing email through public field validation or supplied unique rules', function () {
    User::factory()->create(['email' => 'existing@example.test']);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    Livewire::test(RegisterVendorPage::class)->fillForm(['email' => 'existing@example.test'])
        ->call('validateOnly', 'data.email')->assertHasNoFormErrors(['email'])
        ->call('validateOnly', 'data.email', ['data.email' => ['unique:users,email']])
        ->assertHasNoFormErrors(['email']);
});

it('ignores malicious global validation rules that query account existence', function () {
    User::factory()->create(['email' => 'existing@example.test']);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    Livewire::test(RegisterVendorPage::class)->fillForm(['email' => 'existing@example.test'])
        ->call('validate', ['data.email' => ['unique:users,email']])
        ->assertHasNoFormErrors(['email']);
});

it('clears password values from the Livewire snapshot after failed password validation', function () {
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $payload = privacyRegistrationForm('weak-password@example.test');
    $payload['password'] = $payload['passwordConfirmation'] = 'weakpass';

    $component = Livewire::test(RegisterVendorPage::class)
        ->fillForm($payload)
        ->call('register')
        ->assertHasFormErrors(['password']);

    expect(json_encode($component->snapshot, JSON_THROW_ON_ERROR))
        ->not->toContain('weakpass')
        ->not->toContain('passwordConfirmation');
    expect(User::query()->where('email', 'weak-password@example.test')->exists())->toBeFalse()
        ->and(VendorProfile::query()->count())->toBe(0);
});

it('returns the same neutral guest outcome for new existing and case-variant email addresses', function (string $kind) {
    $existing = User::factory()->create(['email' => 'existing@example.test']);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $email = match ($kind) {
        'new' => 'new@example.test', 'case_variant' => 'EXISTING@example.test', default => $existing->email
    };
    Livewire::test(RegisterVendorPage::class)->fillForm(privacyRegistrationForm($email))->call('register')
        ->assertHasNoFormErrors()->assertRedirect(Filament::getLoginUrl())
        ->assertNotified(__('identity::identity.messages.registration_request_received_title'));
    $this->assertGuest('vendor');
    expect(User::count())->toBe($kind === 'new' ? 2 : 1)
        ->and(VendorProfile::count())->toBe($kind === 'new' ? 1 : 0);
})->with(['new', 'existing', 'case_variant']);

it('retains registration throttling after neutral duplicate outcomes', function () {
    $this->freezeTime();
    User::factory()->create(['email' => 'existing@example.test']);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $payload = privacyRegistrationForm('existing@example.test');
    $component = Livewire::test(RegisterVendorPage::class);
    $component->fillForm($payload)->call('register')->assertHasNoFormErrors();
    $component->fillForm($payload)->call('register')->assertHasNoFormErrors();
    $component->fillForm($payload)->call('register')->assertNotified(
        __('filament-panels::pages/auth/register.notifications.throttled.title', ['seconds' => 60, 'minutes' => 1]),
    );
    expect(User::count())->toBe(1);
    $this->assertGuest('vendor');
});
