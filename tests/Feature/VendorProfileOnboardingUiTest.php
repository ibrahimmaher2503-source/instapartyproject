<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Services\VendorApprovalEligibilityService;
use App\Modules\Identity\Domain\Enums\BusinessType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\VendorProfilePage;
use Filament\Facades\Filament;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;

/** @return array{0: VendorProfile, 1: Governorate, 2: City} */
function profileOnboardingVendor(): array
{
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);
    $profile = VendorProfile::factory()->pending()->create([
        'business_type' => BusinessType::Individual->value,
        'primary_governorate_id' => $governorate->id,
        'primary_city_id' => $city->id,
    ]);

    return [$profile->fresh(), $governorate, $city];
}

it('lets an individual vendor complete the required identity fields from the profile page', function (): void {
    [$profile, $governorate, $city] = profileOnboardingVendor();
    $profile->update(['bank_iban' => 'EG380019000500000000263180002']);
    $user = $profile->user;

    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($user, 'vendor');

    Livewire::test(VendorProfilePage::class)
        ->assertSee(__('identity.fields.national_id'))
        ->fillForm([
            'business_name_en' => 'QA Events',
            'business_name_ar' => 'فعاليات اختبار',
            'address_line_en' => 'QA address',
            'address_line_ar' => 'عنوان اختبار',
            'national_id' => '29901011234567',
            'primary_governorate_id' => $governorate->id,
            'primary_city_id' => $city->id,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($profile->refresh()->national_id)->toBe('29901011234567')
        ->and(app(VendorApprovalEligibilityService::class)->evaluate($profile)->checks['profile'])->toBeTrue();
});

it('persists a complete bilingual address through the vendor profile API without exposing internals', function (): void {
    [$profile] = profileOnboardingVendor();
    $user = $profile->user;

    Sanctum::actingAs($user);

    $response = $this->withHeader('Accept-Language', 'en')
        ->putJson('/api/v1/vendor/profile', [
            'commercial_register_no' => 'CR-1001',
            'tax_id' => 'TAX-1001',
            'national_id' => '29901011234567',
            'address_line' => [
                'en' => '10 API Street',
                'ar' => '١٠ شارع API',
            ],
        ])
        ->assertOk();

    $response->assertJsonPath('data.id', $profile->public_id)
        ->assertJsonMissingPath('data.bank_name')
        ->assertJsonMissingPath('data.bank_account_holder')
        ->assertJsonMissingPath('data.bank_iban')
        ->assertJsonMissingPath('data.bank_swift_bic')
        ->assertJsonMissingPath('data.bank_branch')
        ->assertJsonMissingPath('data.national_id')
        ->assertJsonMissingPath('data.internal_id');

    expect($profile->refresh()->commercial_register_no)->toBe('CR-1001')
        ->and($profile->tax_id)->toBe('TAX-1001')
        ->and($profile->national_id)->toBe('29901011234567')
        ->and($profile->getTranslation('address_line', 'en'))->toBe('10 API Street')
        ->and($profile->getTranslation('address_line', 'ar'))->toBe('١٠ شارع API');
});

it('requires both nonempty address translations through the vendor profile API', function (): void {
    [$profile] = profileOnboardingVendor();

    Sanctum::actingAs($profile->user);

    $this->putJson('/api/v1/vendor/profile', [
        'address_line' => ['en' => '10 API Street'],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['address_line.ar']);

    $this->putJson('/api/v1/vendor/profile', [
        'address_line' => ['ar' => '١٠ شارع API'],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['address_line.en']);

    $this->putJson('/api/v1/vendor/profile', [
        'address_line' => ['en' => '', 'ar' => '١٠ شارع API'],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['address_line.en']);
});

it('persists the browser onboarding profile and banking payload through the Livewire form', function (): void {
    [$profile, $governorate, $city] = profileOnboardingVendor();
    $user = $profile->user;

    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($user, 'vendor');

    Livewire::test(VendorProfilePage::class)
        ->fillForm([
            'business_name_en' => 'Marketplace Business',
            'business_name_ar' => 'شركة اختبار',
            'address_line_en' => '10 Marketplace Street',
            'address_line_ar' => '10 شارع السوق',
            'national_id' => '29801011234567',
            'primary_governorate_id' => $governorate->id,
            'primary_city_id' => $city->id,
            'bank_name' => 'E2E Test Bank',
            'bank_account_holder' => 'Marketplace Vendor',
            'bank_iban' => 'EG380019000500000000263180002',
            'bank_swift' => 'TESTEGCA',
        ])
        // The governorate select is live in the browser and rerenders the city
        // select. Keep this request boundary in the regression to cover the
        // same ordering as the onboarding journey.
        ->set('data.primary_governorate_id', $governorate->id)
        ->set('data.primary_city_id', $city->id)
        ->call('save')
        ->assertHasNoFormErrors();

    $saved = $profile->refresh();

    expect($saved->getTranslation('business_name', 'en'))->toBe('Marketplace Business')
        ->and($saved->getTranslation('business_name', 'ar'))->toBe('شركة اختبار')
        ->and($saved->getTranslation('address_line', 'en'))->toBe('10 Marketplace Street')
        ->and($saved->getTranslation('address_line', 'ar'))->toBe('10 شارع السوق')
        ->and($saved->national_id)->toBe('29801011234567')
        ->and($saved->getRawOriginal('bank_name'))->toBe('E2E Test Bank')
        ->and($saved->getRawOriginal('bank_account_holder'))->toBe('Marketplace Vendor')
        ->and($saved->getRawOriginal('bank_iban'))->toBe('EG380019000500000000263180002')
        ->and($saved->getRawOriginal('bank_swift_bic'))->toBe('TESTEGCA')
        ->and(app(VendorApprovalEligibilityService::class)->evaluate($saved)->checks['profile'])->toBeTrue()
        ->and(app(VendorApprovalEligibilityService::class)->evaluate($saved)->checks['banking'])->toBeTrue();
});
