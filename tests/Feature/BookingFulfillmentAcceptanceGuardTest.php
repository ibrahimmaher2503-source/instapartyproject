<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\Fulfillment\MarkCompletedAction;
use App\Modules\Booking\Application\Actions\Fulfillment\MarkPreparingAction;
use App\Modules\Booking\Application\DTOs\Fulfillment\FulfillmentEvidenceDto;
use App\Modules\Booking\Application\Services\FulfillmentGuards;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Exceptions\BookingNotEligibleForFulfillmentException;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ConfirmedState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\PaidState;
use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingDetailPage;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;

it('blocks preparing an unaccepted allocation even when payment is captured', function (VendorSubStatus $status, ProductType $type): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $booking = Booking::factory()->create(['lifecycle_status' => ConfirmedState::class, 'payment_status' => PaidState::class]);
    $allocation = BookingVendor::factory()->create(['booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id, 'sub_status' => $status]);
    $item = BookingItem::factory()->{$type->value}()->create(['booking_vendor_id' => $allocation->id, 'item_status' => $type === ProductType::Rental ? 'pending_delivery' : 'pending']);
    $originalState = $item->item_status;

    expect(fn () => app(MarkPreparingAction::class)->execute($item, $vendor, $vendor->user))
        ->toThrow(BookingNotEligibleForFulfillmentException::class);
    expect($item->refresh()->item_status)->toBe($originalState);
})->with([VendorSubStatus::Pending, VendorSubStatus::Modified, VendorSubStatus::Rejected, VendorSubStatus::Cancelled, VendorSubStatus::TimedOut])
    ->with([ProductType::Rental, ProductType::Sale]);

it('retains eligibility for accepted and already progressing allocations', function (VendorSubStatus $status): void {
    $booking = Booking::factory()->create(['lifecycle_status' => ConfirmedState::class, 'payment_status' => PaidState::class]);
    $allocation = BookingVendor::factory()->create(['booking_id' => $booking->id, 'sub_status' => $status]);
    app(FulfillmentGuards::class)->runAll($allocation, $allocation->vendor);
    expect($allocation->refresh()->sub_status)->toBe($status);
})->with([VendorSubStatus::Accepted, VendorSubStatus::InProgress]);

it('rejects further fulfillment transitions after the vendor allocation is complete', function (): void {
    $booking = Booking::factory()->create(['lifecycle_status' => ConfirmedState::class, 'payment_status' => PaidState::class]);
    $allocation = BookingVendor::factory()->create(['booking_id' => $booking->id, 'sub_status' => VendorSubStatus::Completed]);

    expect(fn () => app(FulfillmentGuards::class)->runAll($allocation, $allocation->vendor))
        ->toThrow(BookingNotEligibleForFulfillmentException::class);
});

it('blocks digital completion before vendor acceptance', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $booking = Booking::factory()->create(['lifecycle_status' => ConfirmedState::class, 'payment_status' => PaidState::class]);
    $allocation = BookingVendor::factory()->create(['booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id, 'sub_status' => VendorSubStatus::Pending]);
    $item = BookingItem::factory()->digital()->create(['booking_vendor_id' => $allocation->id, 'item_status' => 'pending']);

    expect(fn () => app(MarkCompletedAction::class)->execute($item, $vendor, $vendor->user, new FulfillmentEvidenceDto))
        ->toThrow(BookingNotEligibleForFulfillmentException::class);
    expect($item->refresh()->item_status)->toBe('pending');
});

it('hides preparation controls on a pending booking detail page', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $allocation = BookingVendor::factory()->create(['vendor_profile_id' => $vendor->id, 'sub_status' => VendorSubStatus::Pending]);
    BookingItem::factory()->sale()->create(['booking_vendor_id' => $allocation->id, 'item_status' => 'pending']);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($vendor->user, 'vendor')
        ->get(VendorBookingDetailPage::getUrl(['bookingVendor' => $allocation->public_id]))
        ->assertOk()->assertDontSeeText(__('vendor-portal.fulfillment.lanes.preparing'));
});
