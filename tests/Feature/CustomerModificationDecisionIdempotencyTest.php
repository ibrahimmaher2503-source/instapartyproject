<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('accepts and replays a customer modification decision through the idempotent API route', function (): void {
    Event::fake();

    $customer = User::factory()->phoneVerified()->asCustomer()->create();
    $vendor = VendorProfile::factory()->approved()->create();
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => CustomerReviewState::class,
    ]);
    $bookingVendor = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
        'sub_status' => VendorSubStatus::Modified,
    ]);
    $modification = BookingModification::factory()->create([
        'booking_vendor_id' => $bookingVendor->id,
        'proposed_by' => $vendor->user_id,
        'status' => ModificationStatus::Pending,
        'expires_at' => now()->addHour(),
    ]);

    $url = "/api/v1/customer/bookings/{$booking->public_id}/modifications/{$modification->public_id}/decide";
    $headers = ['Idempotency-Key' => (string) Str::uuid()];

    $first = $this->actingAs($customer, 'sanctum')
        ->postJson($url, ['decision' => 'accepted'], $headers)
        ->assertOk()
        ->assertJsonPath('data.public_id', $booking->public_id);

    $replay = $this->postJson($url, ['decision' => 'accepted'], $headers)->assertOk();
    $conflict = $this->postJson($url, ['decision' => 'rejected'], $headers)->assertStatus(409);

    expect($replay->json())->toBe($first->json())
        ->and($conflict->json())->not->toBe($first->json())
        ->and($modification->fresh()->status)->toBe(ModificationStatus::CustomerAccepted);
});
