<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Identity\Domain\Models\User;

it('does not offer a service rating while customer review is a booking negotiation state', function (): void {
    $customer = User::factory()->phoneVerified()->asCustomer()->create();
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => CustomerReviewState::class,
    ]);
    $allocation = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'sub_status' => 'pending',
    ]);
    $item = BookingItem::factory()->sale()->create([
        'booking_vendor_id' => $allocation->id,
        'item_status' => 'pending',
    ]);

    $this->actingAs($customer)
        ->get('/en/account/bookings/'.$booking->public_id)
        ->assertOk()
        ->assertDontSee(route('reviews.service.store', $item->public_id), false)
        ->assertSee(__('account.review_unavailable', [], 'en'));
});
