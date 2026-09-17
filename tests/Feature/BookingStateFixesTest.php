<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Actions\CustomerConfirmModifiedBookingAction;
use App\Modules\Booking\Application\DTOs\CustomerModificationDecisionDTO;
use App\Modules\Booking\Application\Listeners\CompleteBookingWhenAllVendorsCompletedListener;
use App\Modules\Booking\Application\Listeners\UpdateBookingPaymentStatusListener;
use App\Modules\Booking\Application\Services\BookingModificationDiffService;
use App\Modules\Booking\Domain\Enums\FulfillmentStatus;
use App\Modules\Booking\Domain\Enums\ModificationChangeKind;
use App\Modules\Booking\Domain\Enums\ModificationStatus;
use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Events\BookingCompleted;
use App\Modules\Booking\Domain\Events\BookingVendorCompleted;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingModificationItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ActiveState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\CustomerReviewState;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceInventoryReservation;
use App\Modules\Catalog\Domain\Models\ServiceSaleDetail;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Domain\Models\Payment;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

it('rebuilds the booking payment projection when a capture event is retried', function (): void {
    $booking = Booking::factory()->create();
    $payment = Payment::factory()->captured()->create([
        'booking_id' => $booking->id,
        'amount_minor' => 12500,
    ]);
    $event = new PaymentCaptured($payment->id, $booking->id, 12500, 'EGP', now());
    $listener = app(UpdateBookingPaymentStatusListener::class);

    $listener->handle($event);
    $listener->handle($event);

    expect($booking->refresh()->amount_paid_minor)->toBe(12500);
});

it('uses both old and new quantities when previewing a modification delta', function (): void {
    $vendor = BookingVendor::factory()->create(['subtotal_minor' => 100]);
    $item = BookingItem::factory()->create([
        'booking_vendor_id' => $vendor->id,
        'unit_price_minor' => 100,
        'quantity' => 1,
        'line_total_minor' => 100,
    ]);

    $diff = app(BookingModificationDiffService::class)->buildDiffSnapshot($vendor->load('items'), [[
        'change_kind' => ModificationChangeKind::Update->value,
        'target_item_public_id' => $item->public_id,
        'payload' => ['unit_price_minor' => 200, 'quantity' => 3],
    ]]);

    expect($diff['after']['subtotal_minor'])->toBe(600);
});

it('applies an added published vendor service with trusted snapshots and a reservation', function (): void {
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
    $service = Service::factory()->sale()->published()->create([
        'vendor_profile_id' => $vendor->id,
        'base_price_minor' => 1000,
    ]);
    ServiceSaleDetail::factory()->create(['service_id' => $service->id, 'stock_quantity' => 5]);
    $modification = BookingModification::factory()->create([
        'booking_vendor_id' => $bookingVendor->id,
        'proposed_by' => $vendor->user_id,
        'status' => ModificationStatus::Pending,
        'expires_at' => now()->addHour(),
    ]);
    BookingModificationItem::create([
        'booking_modification_id' => $modification->id,
        'change_kind' => ModificationChangeKind::Add,
        'payload' => [
            'service_id' => $service->id,
            'product_type' => 'sale',
            'unit_price_minor' => 1000,
            'quantity' => 2,
        ],
    ]);

    app(CustomerConfirmModifiedBookingAction::class)->execute(new CustomerModificationDecisionDTO(
        $booking->id,
        $customer->id,
        $modification->public_id,
        'accepted',
        (string) Str::uuid(),
    ));

    $item = BookingItem::query()->where('service_id', $service->id)->firstOrFail();
    $reservation = ServiceInventoryReservation::query()->where('booking_item_id', $item->id)->firstOrFail();

    expect($item->line_total_minor)->toBe(2000)
        ->and($item->getAttribute('type_snapshot')['service_public_id'])->toBe($service->public_id)
        ->and($reservation->getAttribute('status')->value)->toBe('held');
});

it('releases inventory when a customer accepts an item removal', function (): void {
    Event::fake();
    $customer = User::factory()->phoneVerified()->asCustomer()->create();
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => CustomerReviewState::class,
    ]);
    $bookingVendor = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'sub_status' => VendorSubStatus::Modified,
    ]);
    $item = BookingItem::factory()->create(['booking_vendor_id' => $bookingVendor->id]);
    $reservation = ServiceInventoryReservation::factory()->create([
        'service_id' => $item->getAttribute('service_id'),
        'user_id' => $customer->id,
        'booking_item_id' => $item->id,
        'status' => 'held',
    ]);
    $modification = BookingModification::factory()->create([
        'booking_vendor_id' => $bookingVendor->id,
        'proposed_by' => VendorProfile::query()->findOrFail($bookingVendor->vendor_profile_id)->user_id,
        'status' => ModificationStatus::Pending,
        'expires_at' => now()->addHour(),
    ]);
    BookingModificationItem::create([
        'booking_modification_id' => $modification->id,
        'target_booking_item_id' => $item->id,
        'change_kind' => ModificationChangeKind::Remove,
        'payload' => [],
    ]);

    app(CustomerConfirmModifiedBookingAction::class)->execute(new CustomerModificationDecisionDTO(
        $booking->id,
        $customer->id,
        $modification->public_id,
        'accepted',
        (string) Str::uuid(),
    ));

    expect(BookingItem::query()->find($item->id))->toBeNull()
        ->and($reservation->refresh()->getAttribute('status')->value)->toBe('released')
        ->and((bool) $reservation->getAttribute('released_at'))->toBeTrue();
});

it('completes the parent booking once every vendor allocation is complete', function (): void {
    Event::fake();
    $booking = Booking::factory()->create([
        'lifecycle_status' => ActiveState::class,
    ]);
    $vendor = BookingVendor::factory()->completed()->create(['booking_id' => $booking->id]);
    BookingVendor::factory()->completed()->create(['booking_id' => $booking->id]);
    $vendorProfile = VendorProfile::query()->findOrFail($vendor->vendor_profile_id);
    $vendorUser = User::query()->findOrFail($vendorProfile->user_id);

    app(CompleteBookingWhenAllVendorsCompletedListener::class)->handle(
        new BookingVendorCompleted($vendor, $vendorUser),
    );

    expect($booking->refresh()->lifecycle_status->getValue())->toBe('completed')
        ->and($booking->fulfillment_status)->toBe(FulfillmentStatus::Completed);
    Event::assertDispatched(BookingCompleted::class);
});
