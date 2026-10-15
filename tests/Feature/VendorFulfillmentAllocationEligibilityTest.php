<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\Fulfillment\MarkPreparingAction;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Exceptions\BookingNotEligibleForFulfillmentException;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

it('blocks fulfillment while the vendor allocation is pending or timed out', function (VendorSubStatus $status): void {
    $actor = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $actor->id]);
    $booking = Booking::factory()->confirmed()->create();
    $allocation = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
        'sub_status' => $status,
        'response_deadline' => now()->subSecond(),
        'responded_at' => $status === VendorSubStatus::Pending ? null : now()->subMinute(),
    ]);
    $item = BookingItem::factory()->sale()->create([
        'booking_vendor_id' => $allocation->id,
        'product_type' => ProductType::Sale,
    ]);

    expect(fn () => app(MarkPreparingAction::class)->execute($item, $vendor, $actor))
        ->toThrow(BookingNotEligibleForFulfillmentException::class);

    expect($item->fresh()->item_status)->toBe('pending');
})->with([
    'pending after response deadline' => VendorSubStatus::Pending,
    'timed out' => VendorSubStatus::TimedOut,
]);

it('allows fulfillment for accepted and already active allocations', function (VendorSubStatus $status): void {
    $actor = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $actor->id]);
    $booking = Booking::factory()->confirmed()->create();
    $allocation = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
        'sub_status' => $status,
    ]);
    $item = BookingItem::factory()->sale()->create(['booking_vendor_id' => $allocation->id]);

    $updated = app(MarkPreparingAction::class)->execute($item, $vendor, $actor);

    expect($updated->item_status)->toBe('in_preparation');
})->with([
    'accepted' => VendorSubStatus::Accepted,
    'in progress' => VendorSubStatus::InProgress,
]);

it('returns a localized machine-readable conflict for an unaccepted allocation', function (): void {
    $actor = User::factory()->phoneVerified()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $actor->id]);
    $booking = Booking::factory()->confirmed()->create();
    $allocation = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
        'sub_status' => VendorSubStatus::TimedOut,
        'response_deadline' => now()->subSecond(),
        'responded_at' => now()->subMinute(),
    ]);
    $item = BookingItem::factory()->sale()->create(['booking_vendor_id' => $allocation->id]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/v1/vendor/booking-items/'.$item->public_id.'/transition', [
        'lane' => 'preparing',
    ], ['Accept-Language' => 'ar'])
        ->assertConflict()
        ->assertJsonPath('data', null)
        ->assertJsonPath('errors.0.code', BookingNotEligibleForFulfillmentException::CODE_ALLOCATION_NOT_ELIGIBLE)
        ->assertJsonPath('errors.0.message', 'لا يمكن تنفيذ الخدمة قبل قبول المورد للحجز أو بعد انتهاء مهلة الرد.');
});
