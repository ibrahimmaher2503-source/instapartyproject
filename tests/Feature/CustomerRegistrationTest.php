<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\CustomerProfile;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    config(['services.otp.send_on_registration' => false]);
});

function customerRegistrationPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Customer Registration Test',
        'phone_e164' => '+201000001234',
        'email' => 'customer-registration@example.test',
        'password' => 'SafePassword123!',
        'password_confirmation' => 'SafePassword123!',
        'preferred_locale' => 'en',
        'accepted_terms' => '1',
    ], $overrides);
}

it('registers a customer from the public storefront without a server error', function (): void {
    $this->post('/en/auth/register', customerRegistrationPayload())
        ->assertRedirect('/en/auth/verify')
        ->assertSessionHas('auth.verify_phone', '+201000001234');

    $customer = User::query()->where('email', 'customer-registration@example.test')->firstOrFail();

    expect($customer->hasRole('customer'))->toBeTrue()
        ->and(CustomerProfile::query()->where('user_id', $customer->id)->exists())->toBeTrue();
});

it('returns validation errors for duplicate customer contact values', function (): void {
    User::factory()->create([
        'phone_e164' => '+201000001234',
        'email' => 'customer-registration@example.test',
    ]);

    $payload = customerRegistrationPayload();

    $this->from('/en/auth/register')
        ->post('/en/auth/register', $payload)
        ->assertRedirect('/en/auth/register')
        ->assertSessionHasErrors(['phone_e164', 'email']);

    $this->postJson('/api/v1/register/customer', $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone_e164', 'email']);
});
