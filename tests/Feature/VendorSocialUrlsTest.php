<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Actions\RegisterVendorAction;
use App\Modules\Identity\Application\Actions\UpdateVendorProfileAction;
use App\Modules\Identity\Application\DTOs\RegisterVendorDTO;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\RegisterVendorPage;
use App\Modules\Identity\Filament\Vendor\Pages\VendorProfilePage;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Notification::fake();
    Queue::fake();
    Role::findOrCreate('vendor', 'web');
});

function socialUrlRegistrationPayload(): array
{
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);

    return [
        'name' => 'Social URL Vendor',
        'email' => 'social-vendor@example.test',
        'phone_e164' => '+201000009991',
        'password' => 'SafePassword123!',
        'password_confirmation' => 'SafePassword123!',
        'business_name' => ['en' => 'Social Events', 'ar' => 'فعاليات التواصل'],
        'business_type' => 'individual',
        'primary_governorate_id' => $governorate->id,
        'primary_city_id' => $city->id,
        'facebook_url' => 'https://www.facebook.com/social-events',
        'website_url' => 'https://social-events.example',
        'instagram_url' => 'https://www.instagram.com/social-events',
    ];
}

it('persists optional social URLs through registration and profile updates in both locales', function (string $locale): void {
    $payload = socialUrlRegistrationPayload();
    $payload['website_url'] = '  https://social-events.example  ';
    $this->withHeader('Accept-Language', $locale)
        ->postJson('/api/v1/register/vendor', $payload)
        ->assertCreated()
        ->assertJsonPath('data.website_url', 'https://social-events.example')
        ->assertJsonPath('data.facebook_url', $payload['facebook_url'])
        ->assertJsonPath('data.instagram_url', $payload['instagram_url']);

    $profile = VendorProfile::query()->sole();
    Sanctum::actingAs($profile->user);
    $this->putJson('/api/v1/vendor/profile', ['website_url' => 'https://new-events.example'])
        ->assertOk()
        ->assertJsonPath('data.website_url', 'https://new-events.example')
        ->assertJsonPath('data.facebook_url', $payload['facebook_url']);

    $this->putJson('/api/v1/vendor/profile', ['facebook_url' => null, 'website_url' => '', 'instagram_url' => null])
        ->assertOk()
        ->assertJsonPath('data.facebook_url', null)
        ->assertJsonPath('data.website_url', null)
        ->assertJsonPath('data.instagram_url', null);

    expect($profile->refresh()->only(['facebook_url', 'website_url', 'instagram_url']))
        ->toBe(['facebook_url' => null, 'website_url' => null, 'instagram_url' => null]);
})->with(['en', 'ar']);

it('keeps empty links optional when the registration action is called directly', function (): void {
    $payload = socialUrlRegistrationPayload();
    $payload['facebook_url'] = '   ';
    $payload['website_url'] = '';
    unset($payload['instagram_url']);

    $profile = app(RegisterVendorAction::class)->execute(RegisterVendorDTO::fromArray($payload));

    expect($profile->facebook_url)->toBeNull()
        ->and($profile->website_url)->toBeNull()
        ->and($profile->instagram_url)->toBeNull();
});

it('rejects invalid registration links atomically with localized feedback', function (string $locale): void {
    $payload = socialUrlRegistrationPayload();
    foreach (['facebook_url', 'website_url', 'instagram_url'] as $field) {
        $payload[$field] = 'not-a-url';
    }

    $response = $this->withHeader('Accept-Language', $locale)
        ->postJson('/api/v1/register/vendor', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['facebook_url', 'website_url', 'instagram_url']);

    expect($response->json('errors.website_url.0'))->toContain($locale === 'ar' ? 'رابطًا صالحًا' : 'valid URL')
        ->and(User::query()->count())->toBe(0)
        ->and(VendorProfile::query()->count())->toBe(0);
})->with(['en', 'ar']);

it('rejects an invalid link in the direct registration action before creating records', function (): void {
    $payload = socialUrlRegistrationPayload();
    $payload['website_url'] = 'javascript:alert(1)';

    expect(fn () => app(RegisterVendorAction::class)->execute(RegisterVendorDTO::fromArray($payload)))
        ->toThrow(ValidationException::class);
    expect(User::query()->count())->toBe(0)
        ->and(VendorProfile::query()->count())->toBe(0);
});

it('rejects invalid update links without saving other fields in both locales', function (string $locale): void {
    $profile = VendorProfile::factory()->create(['website_url' => 'https://original.example']);
    Sanctum::actingAs($profile->user);
    $this->withHeader('Accept-Language', $locale)
        ->putJson('/api/v1/vendor/profile', [
            'website_url' => 'invalid-url',
            'facebook_url' => 'invalid-url',
            'instagram_url' => 'invalid-url',
            'bio' => ['en' => 'Must not save', 'ar' => 'لا يحفظ'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['website_url', 'facebook_url', 'instagram_url']);

    expect($profile->refresh()->website_url)->toBe('https://original.example')
        ->and($profile->getTranslations('bio'))->toBe([]);

    expect(fn () => app(UpdateVendorProfileAction::class)->execute($profile, ['website_url' => 'invalid-url']))
        ->toThrow(ValidationException::class);
    expect($profile->refresh()->website_url)->toBe('https://original.example');
})->with(['en', 'ar']);

it('saves registration links and edits them through the localized Filament forms', function (string $locale): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $payload = socialUrlRegistrationPayload();

    Livewire::test(RegisterVendorPage::class)
        ->assertSee(__('vendor-portal.profile.facebook_url'))
        ->assertSee(__('vendor-portal.profile.website_url'))
        ->assertSee(__('vendor-portal.profile.instagram_url'))
        ->fillForm([
            ...$payload,
            'business_name_en' => $payload['business_name']['en'],
            'business_name_ar' => $payload['business_name']['ar'],
            'passwordConfirmation' => $payload['password_confirmation'],
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    $profile = VendorProfile::query()->sole();
    expect($profile->website_url)->toBe($payload['website_url']);
    $this->actingAs($profile->user, 'vendor');

    Livewire::test(VendorProfilePage::class)
        ->assertFormSet(['website_url' => $payload['website_url']])
        ->fillForm([
            'address_line_en' => '10 Test Street',
            'address_line_ar' => '١٠ شارع الاختبار',
            'primary_city_id' => $payload['primary_city_id'],
            'national_id' => '29901011234567',
            'facebook_url' => null,
            'website_url' => 'https://updated.example',
            'instagram_url' => 'https://www.instagram.com/updated-events',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($profile->refresh()->facebook_url)->toBeNull()
        ->and($profile->website_url)->toBe('https://updated.example')
        ->and($profile->instagram_url)->toBe('https://www.instagram.com/updated-events');

    Livewire::test(VendorProfilePage::class)
        ->fillForm(['website_url' => 'not-a-url'])
        ->call('save')
        ->assertHasFormErrors(['website_url' => 'url']);

    expect($profile->refresh()->website_url)->toBe('https://updated.example');
})->with(['en', 'ar']);
