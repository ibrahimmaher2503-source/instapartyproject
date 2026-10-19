<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps the draft cart and shows an inline error when submission fails', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
    ]);

    $this->actingAs($customer, 'web')
        ->post('/en/cart/'.$booking->public_id.'/submit')
        ->assertRedirect('/en/cart')
        ->assertSessionHasErrors('cart');

    expect($booking->fresh()->lifecycle_status->getValue())->toBe('draft');
});
