<?php

declare(strict_types=1);

use App\Modules\Booking\Database\Factories\BookingFactory;
use App\Modules\Booking\Database\Factories\BookingItemFactory;
use App\Modules\Booking\Database\Factories\BookingVendorFactory;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\ConfirmedState;
use App\Modules\Booking\Domain\States\BookingLifecycleStatus\VendorReviewState;
use App\Modules\Booking\Domain\States\BookingPaymentStatus\UnpaidState;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\PendingState;
use Laravel\Sanctum\Sanctum;
use Tests\Fakes\SuccessfulPaymentGateway;

function paymentReadyBooking(User $customer): Booking
{
    $booking = BookingFactory::new()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => ConfirmedState::class,
        'payment_status' => UnpaidState::class,
        'total_minor' => 10000,
        'total_currency' => 'EGP',
    ]);
    $bookingVendor = BookingVendorFactory::new()->create(['booking_id' => $booking->id]);
    BookingItemFactory::new()->sale()->create([
        'booking_vendor_id' => $bookingVendor->id,
        'unit_price_minor' => 10000,
        'line_total_minor' => 10000,
        'quantity' => 1,
    ]);

    return $booking;
}

it('initiates one deterministic payment and replays the same idempotent response', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = paymentReadyBooking($customer);

    expect(app(PaymentGateway::class))->toBeInstanceOf(SuccessfulPaymentGateway::class);

    $headers = [
        'Accept' => 'application/json',
        'Accept-Language' => 'en',
        'Idempotency-Key' => 'e2e-payment-contract-0001',
    ];

    Sanctum::actingAs($customer);
    $first = $this->withHeaders($headers)
        ->postJson('/api/v1/customer/bookings/'.$booking->public_id.'/payments', ['method' => 'card'])
        ->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.redirect_url', 'https://testing.invalid/payments/'.$booking->public_id)
        ->assertJsonPath('meta.locale', 'en')
        ->assertJsonPath('meta.direction', 'ltr');

    $second = $this->withHeaders($headers)
        ->postJson('/api/v1/customer/bookings/'.$booking->public_id.'/payments', ['method' => 'card'])
        ->assertCreated();

    $payment = Payment::query()->where('booking_id', $booking->id)->firstOrFail();

    expect($second->json('data.payment_public_id'))->toBe($first->json('data.payment_public_id'))
        ->and(Payment::query()->where('booking_id', $booking->id)->count())->toBe(1)
        ->and($payment->method->value)->toBe('card')
        ->and($payment->status->getValue())->toBe(PendingState::$name)
        ->and($payment->amount_minor)->toBe(10000)
        ->and($payment->amount_currency)->toBe('EGP')
        ->and($payment->gateway_ref)->toBe('test-'.$booking->public_id)
        // Ledger posting belongs to capture; initiation leaves this link empty.
        ->and($payment->capture_ledger_group_id)->toBeNull();
});

it('rejects payment initiation for an unconfirmed booking before the gateway', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $booking = BookingFactory::new()->draft()->create([
        'customer_id' => $customer->id,
        'total_minor' => 10000,
        'total_currency' => 'EGP',
    ]);

    Sanctum::actingAs($customer);
    $this->withHeaders([
        'Accept' => 'application/json',
        'Idempotency-Key' => 'e2e-payment-contract-0002',
    ])
        ->postJson('/api/v1/customer/bookings/'.$booking->public_id.'/payments', ['method' => 'card'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.0.message', 'Booking is not ready for payment');

    expect(Payment::query()->where('booking_id', $booking->id)->exists())->toBeFalse();
});

it('confirms a single-vendor booking through the existing vendor accept route', function (): void {
    $customer = User::factory()->asCustomer()->create();
    $vendorUser = User::factory()->asVendor()->create();
    $vendor = VendorProfile::factory()->approved()->create(['user_id' => $vendorUser->id]);
    $booking = BookingFactory::new()->create([
        'customer_id' => $customer->id,
        'lifecycle_status' => VendorReviewState::class,
    ]);
    $bookingVendor = BookingVendorFactory::new()->create([
        'booking_id' => $booking->id,
        'vendor_profile_id' => $vendor->id,
    ]);

    Sanctum::actingAs($vendorUser);
    $this->withHeaders([
        'Accept' => 'application/json',
        'Accept-Language' => 'en',
    ])
        ->postJson('/api/v1/vendor/booking-vendors/'.$bookingVendor->public_id.'/accept', [])
        ->assertOk()
        ->assertJsonPath('data.public_id', $bookingVendor->public_id)
        ->assertJsonPath('data.sub_status', 'accepted');

    expect($booking->fresh()->lifecycle_status->getValue())->toBe('confirmed');
});
