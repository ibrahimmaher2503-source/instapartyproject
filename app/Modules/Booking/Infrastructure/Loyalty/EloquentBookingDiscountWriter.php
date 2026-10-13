<?php

declare(strict_types=1);

namespace App\Modules\Booking\Infrastructure\Loyalty;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Loyalty\Domain\Contracts\BookingDiscountWriter;
use Brick\Money\Money;
use DomainException;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class EloquentBookingDiscountWriter implements BookingDiscountWriter
{
    /**
     * Apply a loyalty discount to a booking by writing
     * `loyalty_redeemed_minor` + `loyalty_redemption_public_id` and recomputing
     * `total_minor = subtotal + delivery - discount_total - loyalty_redeemed`.
     */
    public function applyLoyaltyDiscount(int $bookingId, int $discountMinor, string $currency, string $redemptionPublicId): int
    {
        return DB::transaction(function () use ($bookingId, $discountMinor, $currency, $redemptionPublicId): int {
            $booking = Booking::query()->lockForUpdate()->find($bookingId);

            if ($booking === null) {
                throw new RuntimeException("Booking {$bookingId} not found.");
            }

            $bookingCurrency = (string) ($booking->subtotal_currency ?: 'EGP');
            if ($currency !== $bookingCurrency || (string) ($booking->total_currency ?: 'EGP') !== $bookingCurrency) {
                throw new DomainException('loyalty.currency_mismatch');
            }

            foreach ([
                ['subtotal_minor', 'subtotal_currency'],
                ['delivery_total_minor', 'delivery_total_currency'],
                ['discount_total_minor', 'discount_total_currency'],
                ['loyalty_redeemed_minor', 'loyalty_redeemed_currency'],
                ['transport_base_minor', 'transport_base_currency'],
            ] as [$amountField, $currencyField]) {
                if ((int) $booking->{$amountField} > 0 && (string) $booking->{$currencyField} !== $bookingCurrency) {
                    throw new DomainException('loyalty.currency_mismatch');
                }
            }

            $vendorCurrencyMismatch = DB::table('booking_vendors')
                ->where('booking_id', $booking->id)
                ->where(function ($query) use ($bookingCurrency): void {
                    $query->where(function ($query) use ($bookingCurrency): void {
                        $query->where('subtotal_minor', '>', 0)->where('subtotal_currency', '<>', $bookingCurrency);
                    })->orWhere(function ($query) use ($bookingCurrency): void {
                        $query->where('delivery_fee_minor', '>', 0)->where('delivery_fee_currency', '<>', $bookingCurrency);
                    });
                })
                ->exists();

            $adjustmentCurrencyMismatch = DB::table('booking_adjustments')
                ->where('booking_id', $booking->id)
                ->where('type', 'transportation')
                ->where('amount_minor', '>', 0)
                ->where('currency', '<>', $bookingCurrency)
                ->exists();

            if ($vendorCurrencyMismatch || $adjustmentCurrencyMismatch) {
                throw new DomainException('loyalty.currency_mismatch');
            }

            $newTotal = Money::ofMinor((int) $booking->subtotal_minor, $bookingCurrency)
                ->plus(Money::ofMinor((int) $booking->delivery_total_minor, $bookingCurrency))
                ->minus(Money::ofMinor((int) $booking->discount_total_minor, $bookingCurrency))
                ->minus(Money::ofMinor($discountMinor, $bookingCurrency));

            if ($newTotal->isNegative()) {
                throw new DomainException('loyalty.discount_exceeds_total');
            }

            $booking->forceFill([
                'loyalty_redeemed_minor' => $discountMinor,
                'loyalty_redeemed_currency' => $bookingCurrency,
                'loyalty_redemption_public_id' => $redemptionPublicId,
                'total_minor' => $newTotal->getMinorAmount()->toInt(),
                'total_currency' => $bookingCurrency,
            ])->save();

            return $newTotal->getMinorAmount()->toInt();
        });
    }

    public function removeLoyaltyDiscount(int $bookingId, string $redemptionPublicId): void
    {
        DB::transaction(function () use ($bookingId, $redemptionPublicId): void {
            $booking = Booking::query()->lockForUpdate()->find($bookingId);

            if ($booking === null) {
                throw new RuntimeException("Booking {$bookingId} not found.");
            }

            // Idempotent: only clear if this redemption is the one currently applied.
            if ($booking->loyalty_redemption_public_id !== $redemptionPublicId) {
                return;
            }

            $currency = (string) ($booking->total_currency ?: 'EGP');
            foreach ([
                ['subtotal_minor', 'subtotal_currency'],
                ['delivery_total_minor', 'delivery_total_currency'],
                ['discount_total_minor', 'discount_total_currency'],
            ] as [$amountField, $currencyField]) {
                if ((int) $booking->{$amountField} > 0 && (string) $booking->{$currencyField} !== $currency) {
                    throw new DomainException('loyalty.currency_mismatch');
                }
            }
            $newTotal = Money::ofMinor((int) $booking->subtotal_minor, $currency)
                ->plus(Money::ofMinor((int) $booking->delivery_total_minor, $currency))
                ->minus(Money::ofMinor((int) $booking->discount_total_minor, $currency));

            $booking->forceFill([
                'loyalty_redeemed_minor' => 0,
                'loyalty_redeemed_currency' => $currency,
                'loyalty_redemption_public_id' => null,
                'total_minor' => Money::max(Money::ofMinor(0, $currency), $newTotal)->getMinorAmount()->toInt(),
                'total_currency' => $currency,
            ])->save();
        });
    }
}
