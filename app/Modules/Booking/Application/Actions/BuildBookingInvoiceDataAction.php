<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Payments\Domain\Contracts\BookingPaymentTotalsReader;
use Brick\Money\Money;

final class BuildBookingInvoiceDataAction
{
    public function __construct(private readonly BookingPaymentTotalsReader $payments) {}

    /** @return array<string, mixed> */
    public function execute(string $bookingPublicId): array
    {
        $booking = Booking::query()->where('public_id', $bookingPublicId)
            ->with(['customer', 'address.city.governorate', 'vendors.vendor', 'vendors.items', 'payments', 'adjustments'])
            ->firstOrFail();
        $currency = (string) $booking->subtotal_currency;
        foreach ([
            ['total_minor', 'total_currency'], ['delivery_total_minor', 'delivery_total_currency'],
            ['discount_total_minor', 'discount_total_currency'], ['loyalty_redeemed_minor', 'loyalty_redeemed_currency'],
            ['transport_base_minor', 'transport_base_currency'],
        ] as [$amountField, $currencyField]) {
            if ((int) $booking->{$amountField} !== 0 && (string) $booking->{$currencyField} !== $currency) {
                abort(409, __('booking::booking.invoice.currency_mismatch'));
            }
        }
        $itemSubtotal = Money::ofMinor(0, $currency);
        $vendorDelivery = Money::ofMinor(0, $currency);
        foreach ($booking->vendors as $vendor) {
            foreach ($vendor->items as $item) {
                if ($item->line_total_currency !== $currency) {
                    abort(409, __('booking::booking.invoice.currency_mismatch'));
                }
                $itemSubtotal = $itemSubtotal->plus(Money::ofMinor((int) $item->line_total_minor, $currency));
            }
            if ((int) $vendor->delivery_fee_minor !== 0 && $vendor->delivery_fee_currency !== $currency) {
                abort(409, __('booking::booking.invoice.currency_mismatch'));
            }
            $vendorDelivery = $vendorDelivery->plus(Money::ofMinor((int) $vendor->delivery_fee_minor, $currency));
        }
        $adjustments = $booking->adjustments->where('type', 'transportation');
        $transportAdjustment = Money::ofMinor(0, $currency);
        foreach ($adjustments as $adjustment) {
            if ($adjustment->currency !== $currency) {
                abort(409, __('booking::booking.invoice.currency_mismatch'));
            }
            $transportAdjustment = $transportAdjustment->plus(Money::ofMinor((int) $adjustment->amount_minor, $currency));
        }
        $calculatedDelivery = $vendorDelivery->plus($transportAdjustment)
            ->plus(Money::ofMinor((int) $booking->transport_base_minor, $currency));
        $recordedDelivery = Money::ofMinor((int) $booking->delivery_total_minor, $currency);
        $calculatedTotal = Money::ofMinor((int) $booking->subtotal_minor, $currency)
            ->plus($recordedDelivery)
            ->minus(Money::ofMinor((int) $booking->discount_total_minor, $currency))
            ->minus(Money::ofMinor((int) $booking->loyalty_redeemed_minor, $currency));
        $paymentTotals = $this->payments->forBooking($booking->id, (string) $booking->total_currency);
        $paymentStatus = match (true) {
            $paymentTotals['has_pending_refund'] => 'refund_pending',
            $paymentTotals['refunded_minor'] > 0 && $paymentTotals['net_paid_minor'] === 0 => 'refunded',
            $booking->payment_status->getValue() === 'refund_pending' => 'refund_pending',
            $paymentTotals['refunded_minor'] > 0 => 'partially_refunded',
            $paymentTotals['captured_minor'] >= (int) $booking->total_minor && $paymentTotals['captured_minor'] > 0 => 'paid',
            $paymentTotals['captured_minor'] > 0 => 'partial',
            default => 'unpaid',
        };

        return [
            'booking' => $booking,
            'calculationCurrency' => $currency,
            'format' => fn (int $minor, string $moneyCurrency): string => Money::ofMinor($minor, $moneyCurrency)->formatTo(app()->getLocale()),
            'vendorDeliveryMinor' => $vendorDelivery->getMinorAmount()->toInt(),
            'transportAdjustmentMinor' => $transportAdjustment->getMinorAmount()->toInt(),
            'adjustments' => $adjustments,
            'itemSubtotalMinor' => $itemSubtotal->getMinorAmount()->toInt(),
            'subtotalReconciliationMinor' => Money::ofMinor((int) $booking->subtotal_minor, $currency)
                ->minus($itemSubtotal)->getMinorAmount()->toInt(),
            'deliveryReconciliationMinor' => $recordedDelivery->minus($calculatedDelivery)->getMinorAmount()->toInt(),
            'totalReconciliationMinor' => Money::ofMinor((int) $booking->total_minor, $currency)
                ->minus($calculatedTotal)->getMinorAmount()->toInt(),
            'paymentTotals' => $paymentTotals,
            'paymentStatus' => $paymentStatus,
        ];
    }
}
