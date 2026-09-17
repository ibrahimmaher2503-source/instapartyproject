<?php

declare(strict_types=1);

use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Identity\Application\Listeners\SendVendorRegistrationEmailVerification;
use App\Modules\Identity\Application\Listeners\SendVendorRegistrationVerification;
use App\Modules\Identity\Domain\Events\VendorRegistered;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\RegisterVendorPage;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Mail::fake();
    Notification::fake();
    Queue::fake();
    Role::findOrCreate('vendor', 'web');
});

function vendorRegistrationGeography(): array
{
    $governorate = Governorate::factory()->create();
    $city = City::factory()->create(['governorate_id' => $governorate->id]);

    return [$governorate, $city];
}

function vendorRegistrationPayload(Governorate $governorate, City $city, string $suffix = 'one'): array
{
    return [
        'name' => 'QA Vendor '.$suffix,
        'email' => ' QA-'.$suffix.'@EXAMPLE.TEST ',
        'phone_e164' => '+20 100 000 0'.str_pad((string) abs(crc32($suffix) % 1000), 3, '0', STR_PAD_LEFT),
        'password' => 'SafePassword123!',
        'password_confirmation' => 'SafePassword123!',
        'business_name' => ['en' => 'QA Events '.$suffix, 'ar' => 'QA Events '.$suffix],
        'business_type' => 'individual',
        'primary_governorate_id' => $governorate->id,
        'primary_city_id' => $city->id,
        'preferred_locale' => 'en',
    ];
}

it('registers through the API atomically and returns verification pending', function (): void {
    [$governorate, $city] = vendorRegistrationGeography();

    $response = $this->postJson('/api/v1/register/vendor', vendorRegistrationPayload($governorate, $city));

    $response->assertCreated()
        ->assertJsonPath('meta.registration_successful', true)
        ->assertJsonPath('meta.verification_pending', true);

    expect(User::query()->count())->toBe(1)
        ->and(VendorProfile::query()->count())->toBe(1)
        ->and(User::query()->firstOrFail()->email)->toBe('qa-one@example.test')
        ->and(User::query()->firstOrFail()->phone_e164)->not->toContain(' ');
});

it('rolls back and localizes validation for confirmation and governorate-city failures', function (string $locale): void {
    [$governorate] = vendorRegistrationGeography();
    [, $otherCity] = vendorRegistrationGeography();
    $payload = vendorRegistrationPayload($governorate, $otherCity, $locale);
    $payload['password_confirmation'] = 'DifferentPassword123!';

    $this->withHeader('Accept-Language', $locale)
        ->postJson('/api/v1/register/vendor', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password', 'primary_city_id']);

    expect(User::query()->count())->toBe(0)
        ->and(VendorProfile::query()->count())->toBe(0);
})->with(['en', 'ar']);

it('rejects soft-deleted email and phone collisions without partial records', function (): void {
    [$governorate, $city] = vendorRegistrationGeography();
    $payload = vendorRegistrationPayload($governorate, $city, 'duplicate');
    $existing = User::factory()->create([
        'email' => strtolower(trim($payload['email'])),
        'phone_e164' => str_replace([' ', '-', '(', ')'], '', $payload['phone_e164']),
    ]);
    $existing->delete();

    $this->postJson('/api/v1/register/vendor', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'phone_e164']);

    expect(VendorProfile::query()->count())->toBe(0);
});

it('keeps registration successful when an after-commit delivery listener fails', function (): void {
    [$governorate, $city] = vendorRegistrationGeography();
    Event::listen(VendorRegistered::class, static fn () => throw new RuntimeException('simulated delivery outage'));

    $this->postJson('/api/v1/register/vendor', vendorRegistrationPayload($governorate, $city, 'delivery'))
        ->assertCreated()
        ->assertJsonPath('meta.verification_pending', true);

    expect(User::query()->where('email', 'qa-delivery@example.test')->exists())->toBeTrue()
        ->and(VendorProfile::query()->count())->toBe(1);
});

it('configures independent retryable after-commit email and OTP delivery', function (): void {
    $email = app(SendVendorRegistrationEmailVerification::class);
    $otp = app(SendVendorRegistrationVerification::class);

    expect($email->afterCommit)->toBeTrue()
        ->and($email->tries)->toBe(3)
        ->and($otp->afterCommit)->toBeTrue()
        ->and($otp->tries)->toBe(3);
});

it('registers through the Filament Livewire page with the confirmed field mapped', function (): void {
    [$governorate, $city] = vendorRegistrationGeography();
    Filament::setCurrentPanel(Filament::getPanel('vendor'));

    Livewire::test(RegisterVendorPage::class)
        ->fillForm([
            'name' => 'QA Livewire Vendor',
            'email' => 'qa-livewire@example.test',
            'phone_e164' => '+201000009999',
            'password' => 'SafePassword123!',
            'passwordConfirmation' => 'SafePassword123!',
            'business_name_en' => 'QA Livewire Events',
            'business_name_ar' => 'QA Livewire Events AR',
            'business_type' => 'individual',
            'primary_governorate_id' => $governorate->id,
            'primary_city_id' => $city->id,
        ])
        ->call('register')
        ->assertHasNoFormErrors();

    expect(User::query()->where('email', 'qa-livewire@example.test')->exists())->toBeTrue()
        ->and(VendorProfile::query()->count())->toBe(1);
});

it('rejects weak passwords and active duplicate contact values without partial records', function (): void {
    [$governorate, $city] = vendorRegistrationGeography();
    $duplicate = vendorRegistrationPayload($governorate, $city, 'active-duplicate');
    User::factory()->create([
        'email' => strtolower(trim($duplicate['email'])),
        'phone_e164' => str_replace([' ', '-', '(', ')'], '', $duplicate['phone_e164']),
    ]);

    $this->postJson('/api/v1/register/vendor', $duplicate)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'phone_e164']);

    $weak = vendorRegistrationPayload($governorate, $city, 'weak');
    $weak['password'] = $weak['password_confirmation'] = 'short';
    $this->postJson('/api/v1/register/vendor', $weak)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);

    expect(VendorProfile::query()->count())->toBe(0);
});

it('normalizes Filament contact values before validation and redirects to verification', function (): void {
    [$governorate, $city] = vendorRegistrationGeography();
    Filament::setCurrentPanel(Filament::getPanel('vendor'));

    Livewire::test(RegisterVendorPage::class)
        ->fillForm([
            'name' => 'QA Normalized Vendor',
            'email' => ' QA-FILAMENT@EXAMPLE.TEST ',
            'phone_e164' => '+20 100 000 7777',
            'password' => 'SafePassword123!',
            'passwordConfirmation' => 'SafePassword123!',
            'business_name_en' => 'QA Normalized Events',
            'business_name_ar' => 'فعاليات اختبار',
            'business_type' => 'individual',
            'primary_governorate_id' => $governorate->id,
            'primary_city_id' => $city->id,
        ])
        ->call('register')
        ->assertHasNoFormErrors()
        ->assertRedirect(Filament::getEmailVerificationPromptUrl());

    expect(User::query()->where('email', 'qa-filament@example.test')->exists())->toBeTrue()
        ->and(User::query()->where('phone_e164', '+201000007777')->exists())->toBeTrue();
});

it('does not create a second account when the normalized registration is submitted again', function (): void {
    [$governorate, $city] = vendorRegistrationGeography();
    $payload = vendorRegistrationPayload($governorate, $city, 'repeat');

    $this->postJson('/api/v1/register/vendor', $payload)->assertCreated();
    $this->postJson('/api/v1/register/vendor', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'phone_e164']);

    expect(User::query()->where('email', 'qa-repeat@example.test')->count())->toBe(1)
        ->and(VendorProfile::query()->count())->toBe(1);
});
