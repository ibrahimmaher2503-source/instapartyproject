<?php

declare(strict_types=1);

use App\Modules\Booking\Application\Listeners\UpdateBookingPaymentStatusListener;
use App\Modules\Booking\Domain\Events\BookingItemAdded;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Infrastructure\Loyalty\EloquentBookingDiscountWriter;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Events\PaymentCaptured;
use App\Modules\Payments\Domain\Models\Payment;
use Brick\Money\Money;
use Illuminate\Support\Str;

it('reconciles persisted item, booking discounts, and captured payment in the invoice', function (string $locale): void {
    $admin = User::factory()->create();
    $admin->assignRole('admin');

    $booking = Booking::factory()->create([
        'discount_total_minor' => 50,
        'discount_total_currency' => 'EGP',
    ]);
    $vendor = BookingVendor::factory()->create([
        'booking_id' => $booking->id,
        'subtotal_minor' => 0,
    ]);
    $item = BookingItem::factory()->create([
        'booking_vendor_id' => $vendor->id,
        'name_snapshot' => ['en' => 'Gift package', 'ar' => 'باقة هدايا'],
        'unit_price_minor' => 1_000,
        'line_total_minor' => 1_000,
        'line_total_currency' => 'EGP',
        'quantity' => 1,
    ]);

    event(new BookingItemAdded($item->load('bookingVendor')));

    app(EloquentBookingDiscountWriter::class)->applyLoyaltyDiscount(
        $booking->id,
        100,
        'EGP',
        (string) Str::ulid(),
    );

    $payment = Payment::factory()->captured()->create([
        'booking_id' => $booking->id,
        'user_id' => $booking->customer_id,
        'amount_minor' => 850,
        'amount_currency' => 'EGP',
    ]);
    app(UpdateBookingPaymentStatusListener::class)->handle(new PaymentCaptured(
        $payment->id,
        $booking->id,
        850,
        'EGP',
        now(),
    ));

    $booking->refresh();

    expect($booking->subtotal_minor)->toBe(1_000)
        ->and($booking->discount_total_minor)->toBe(50)
        ->and($booking->loyalty_redeemed_minor)->toBe(100)
        ->and($booking->total_minor)->toBe(850)
        ->and($booking->amount_paid_minor)->toBe(850);

    $this->actingAs($admin, 'web')
        ->get(route('admin.bookings.invoice', [$booking->public_id, 'lang' => $locale]))
        ->assertOk()
        ->assertSeeText(__('booking.invoice.discount', [], $locale))
        ->assertSeeText(__('booking.invoice.loyalty_redemption', [], $locale))
        ->assertSeeText('−'.Money::ofMinor(50, 'EGP')->formatTo($locale))
        ->assertSeeText('−'.Money::ofMinor(100, 'EGP')->formatTo($locale))
        ->assertSeeText(Money::ofMinor(1_000, 'EGP')->formatTo($locale))
        ->assertSeeText(Money::ofMinor(850, 'EGP')->formatTo($locale));
})->with(['en', 'ar']);
