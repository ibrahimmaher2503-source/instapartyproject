<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Models\User;
use Laravel\Sanctum\Sanctum;

it('blocks suspended customer mutations while keeping history readable', function (): void {
    $customer = User::factory()->asCustomer()->create(['status' => 'suspended']);
    Sanctum::actingAs($customer);

    $this->postJson('/api/v1/customer/bookings')->assertForbidden()->assertJsonPath('errors.0.code', 'account_suspended');
    $this->postJson('/api/v1/customer/bookings/01H00000000000000000000000/payments', [], ['Idempotency-Key' => 'suspended-payment'])
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'account_suspended');
    $this->postJson('/api/v1/customer/booking-items/01H00000000000000000000000/review')
        ->assertForbidden()
        ->assertJsonPath('errors.0.code', 'account_suspended');

    $this->getJson('/api/v1/customer/payments/01H00000000000000000000000')->assertNotFound();
});

it('restricts customer payment endpoints to customer accounts', function (): void {
    $vendor = User::factory()->asVendor()->create();
    Sanctum::actingAs($vendor);

    $this->getJson('/api/v1/customer/payments/01H00000000000000000000000')->assertForbidden();
});
