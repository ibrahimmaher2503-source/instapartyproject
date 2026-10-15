<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\CustomerProfile;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;

beforeEach(function (): void {
    Role::findOrCreate('customer', 'web');
    Role::findOrCreate('vendor', 'web');
    config(['services.otp.send_on_registration' => false]);
});

it('returns only the customer public resource after registration', function (): void {
    $password = 'QaRegistrationPassword123!';

    $response = $this->postJson('/api/v1/register/customer', [
        'name' => 'QA Public Customer',
        'phone_e164' => '+201000001876',
        'email' => 'qa-public-customer@example.test',
        'password' => $password,
        'password_confirmation' => $password,
        'preferred_locale' => 'en',
        'accepted_terms' => '1',
    ])->assertCreated()
        ->assertJsonStructure([
            'data' => [
                'id', 'name', 'email', 'phone_e164', 'phone_verified_at', 'preferred_locale',
                'customer_profile' => ['date_of_birth', 'gender', 'accepts_marketing'],
            ],
            'meta',
            'errors',
        ])
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.password_confirmation')
        ->assertJsonMissingPath('data.remember_token')
        ->assertJsonMissingPath('data.token')
        ->assertJsonMissingPath('data.internal_id')
        ->assertJsonMissingPath('data.customer_profile.user_id')
        ->assertDontSee($password);

    $publicId = $response->json('data.id');
    expect($publicId)->toBeString()->toMatch('/^[0-9A-HJKMNP-TV-Z]{26}$/');
});

it('returns only the authenticated customer and does not serialize internal or other-account fields', function (): void {
    $owner = User::factory()->asCustomer()->create([
        'name' => 'QA Customer Owner',
        'email' => 'qa-customer-owner@example.test',
        'phone_e164' => '+201000001877',
    ]);
    CustomerProfile::factory()->create(['user_id' => $owner->id]);
    $other = User::factory()->asCustomer()->create([
        'name' => 'QA Other Customer',
        'email' => 'qa-other-customer@example.test',
        'phone_e164' => '+201000001878',
    ]);

    Sanctum::actingAs($owner);
    $response = $this->getJson('/api/v1/customer/profile')
        ->assertOk()
        ->assertJsonPath('data.id', $owner->public_id)
        ->assertJsonMissingPath('data.internal_id')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token')
        ->assertJsonMissingPath('data.tokens')
        ->assertJsonMissingPath('data.customer_profile.user_id')
        ->assertDontSee($other->name)
        ->assertDontSee($other->email)
        ->assertDontSee($other->phone_e164);

    expect($response->json('data.id'))->toBe($owner->public_id);
});

it('returns only the signed-in vendor profile and omits identity and banking fields', function (): void {
    $profile = VendorProfile::factory()->pending()->create([
        'business_name' => ['en' => 'QA Privacy Vendor', 'ar' => 'مورد خصوصية الاختبار'],
        'national_id' => 'QA-NATIONAL-ID-SENTINEL',
        'commercial_register_no' => 'QA-REGISTER-SENTINEL',
        'tax_id' => 'QA-TAX-SENTINEL',
        'bank_iban' => 'QA-IBAN-SENTINEL',
    ]);
    $other = VendorProfile::factory()->pending()->create([
        'business_name' => ['en' => 'QA Other Vendor Private Name', 'ar' => 'اسم مورد آخر خاص'],
    ]);

    Sanctum::actingAs($profile->user);
    $response = $this->getJson('/api/v1/vendor/me')
        ->assertOk()
        ->assertJsonPath('data.public_id', $profile->user->public_id)
        ->assertJsonPath('data.vendor_profile.public_id', $profile->public_id)
        ->assertJsonMissingPath('data.id')
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.vendor_profile.id')
        ->assertJsonMissingPath('data.vendor_profile.national_id')
        ->assertJsonMissingPath('data.vendor_profile.commercial_register_no')
        ->assertJsonMissingPath('data.vendor_profile.tax_id')
        ->assertJsonMissingPath('data.vendor_profile.bank_iban')
        ->assertDontSee($other->getTranslation('business_name', 'en'));

    foreach (['QA-NATIONAL-ID-SENTINEL', 'QA-REGISTER-SENTINEL', 'QA-TAX-SENTINEL', 'QA-IBAN-SENTINEL'] as $sentinel) {
        $response->assertDontSee($sentinel);
    }
});
