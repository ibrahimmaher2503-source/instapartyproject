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
use App\Modules\Payments\Domain\Models\Refund;
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

it('prints payment status from the same cash totals without rewriting a historical booking', function (string $locale, int $refundedMinor, string $status): void {
    $admin = User::factory()->superAdmin()->create();
    $booking = Booking::factory()->create([
        'subtotal_minor' => 850,
        'total_minor' => 850,
        'amount_paid_minor' => 1_700,
    ]);
    $payment = Payment::factory()->captured()->create([
        'booking_id' => $booking->id,
        'amount_minor' => 850,
    ]);
    if ($refundedMinor > 0) {
        Refund::factory()->completed()->create([
            'payment_id' => $payment->id,
            'booking_id' => $booking->id,
            'amount_minor' => $refundedMinor,
        ]);
    }

    $this->actingAs($admin, 'web')
        ->get(route('admin.bookings.invoice', [$booking->public_id, 'lang' => $locale]))
        ->assertOk()
        ->assertSeeText(__('booking::booking.payment_status.'.$status, [], $locale))
        ->assertDontSeeText(__('booking::booking.payment_status.unpaid', [], $locale));

    expect($booking->fresh()->amount_paid_minor)->toBe(1_700)
        ->and($booking->fresh()->payment_status->getValue())->toBe('unpaid');
})->with(['en', 'ar'])->with([
    'captured' => [0, 'paid'],
    'partial refund' => [250, 'partially_refunded'],
    'full refund' => [850, 'refunded'],
]);

it('removes only the matching loyalty line and retains recorded delivery and discount', function (): void {
    $redemptionPublicId = (string) Str::ulid();
    $booking = Booking::factory()->create([
        'subtotal_minor' => 1_000,
        'delivery_total_minor' => 300,
        'discount_total_minor' => 50,
        'loyalty_redeemed_minor' => 100,
        'loyalty_redemption_public_id' => $redemptionPublicId,
        'total_minor' => 1_150,
    ]);
    $writer = app(EloquentBookingDiscountWriter::class);
    $writer->removeLoyaltyDiscount($booking->id, (string) Str::ulid());
    expect($booking->fresh()->total_minor)->toBe(1_150);

    $writer->removeLoyaltyDiscount($booking->id, $redemptionPublicId);
    $writer->removeLoyaltyDiscount($booking->id, $redemptionPublicId);
    expect($booking->fresh()->total_minor)->toBe(1_250)
        ->and($booking->fresh()->delivery_total_minor)->toBe(300)
        ->and($booking->fresh()->discount_total_minor)->toBe(50)
        ->and($booking->fresh()->loyalty_redeemed_minor)->toBe(0);
});

it('rejects mixed-currency loyalty removal without clearing the redemption', function (): void {
    $redemptionPublicId = (string) Str::ulid();
    $booking = Booking::factory()->create([
        'subtotal_minor' => 1_000,
        'delivery_total_minor' => 300,
        'delivery_total_currency' => 'USD',
        'loyalty_redeemed_minor' => 100,
        'loyalty_redemption_public_id' => $redemptionPublicId,
        'total_minor' => 1_200,
    ]);
    expect(fn () => app(EloquentBookingDiscountWriter::class)->removeLoyaltyDiscount($booking->id, $redemptionPublicId))
        ->toThrow(DomainException::class, 'loyalty.currency_mismatch');
    expect($booking->fresh()->loyalty_redemption_public_id)->toBe($redemptionPublicId)
        ->and($booking->fresh()->total_minor)->toBe(1_200);
});
