<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Application\DTOs\BookingItemSnapshotDto;
use App\Modules\Settlement\Application\DTOs\RefundSnapshotDto;
use App\Modules\Settlement\Domain\Contracts\SettlementBookingReader;
use App\Modules\Settlement\Domain\Contracts\SettlementPaymentReader;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentCommissionRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\Log;

class ReconcileCompletedRefundsAction
{
    public function __construct(
        private readonly SettlementPaymentReader $paymentReader,
        private readonly SettlementBookingReader $bookingReader,
        private readonly EloquentCommissionRepository $commissionRepo,
        private readonly ReverseCommissionAction $reverseCommission,
    ) {}

    public function execute(int $paymentId): void
    {
        $payment = $this->paymentReader->findById($paymentId);

        if ($payment === null) {
            Log::error('Settlement: cannot reconcile completed refunds for missing payment', [
                'payment_id' => $paymentId,
            ]);

            return;
        }

        $items = $this->bookingReader->itemsForPayment($payment->bookingId);

        if ($items === []) {
            return;
        }

        usort($items, static fn (BookingItemSnapshotDto $left, BookingItemSnapshotDto $right): int => $left->id <=> $right->id);

        $currency = $items[0]->totalCurrency;
        $remainingByItem = [];
        $remainingServiceGross = Money::ofMinor(0, $currency);

        foreach ($items as $item) {
            if ($item->totalCurrency !== $currency || $currency !== $payment->currency) {
                Log::error('Settlement: booking item currency does not match payment while reconciling refunds', [
                    'payment_id' => $paymentId,
                    'booking_item_id' => $item->id,
                    'payment_currency' => $payment->currency,
                    'booking_item_currency' => $item->totalCurrency,
                ]);

                return;
            }

            $itemGross = Money::ofMinor($item->totalMinor, $currency);
            $remainingByItem[$item->id] = $itemGross->getMinorAmount()->toInt();
            $remainingServiceGross = $remainingServiceGross->plus($itemGross);
        }

        if ($remainingServiceGross->isZero()) {
            return;
        }

        $refundedByItem = array_fill_keys(array_keys($remainingByItem), 0);

        foreach ($this->paymentReader->completedRefundsForPayment($paymentId) as $refund) {
            if ($refund->currency !== $currency) {
                Log::error('Settlement: refund currency does not match booking while reconciling commissions', [
                    'payment_id' => $paymentId,
                    'refund_id' => $refund->id,
                    'refund_currency' => $refund->currency,
                    'booking_currency' => $currency,
                ]);

                continue;
            }

            $allocations = $refund->bookingItemId === null
                ? $this->allocateAcrossRemainingItems($refund->amountMinor, $remainingByItem, $currency)
                : $this->allocateToItem($refund->bookingItemId, $refund->amountMinor, $remainingByItem, $currency);

            foreach ($allocations as $bookingItemId => $allocatedMinor) {
                $allocatedMoney = Money::ofMinor($allocatedMinor, $currency);
                $remainingByItem[$bookingItemId] = Money::ofMinor($remainingByItem[$bookingItemId], $currency)
                    ->minus($allocatedMoney)
                    ->getMinorAmount()
                    ->toInt();
                $refundedByItem[$bookingItemId] = Money::ofMinor($refundedByItem[$bookingItemId], $currency)
                    ->plus($allocatedMoney)
                    ->getMinorAmount()
                    ->toInt();

                $commission = $this->commissionRepo->findByBookingItemId($bookingItemId);

                if ($commission === null) {
                    Log::warning('Settlement: commission is not accrued yet for a completed refund allocation', [
                        'payment_id' => $paymentId,
                        'refund_id' => $refund->id,
                        'booking_item_id' => $bookingItemId,
                    ]);

                    continue;
                }

                $allocatedRefund = new RefundSnapshotDto(
                    id: $refund->id,
                    publicId: $refund->publicId,
                    paymentId: $refund->paymentId,
                    bookingItemId: $bookingItemId,
                    amountMinor: $allocatedMinor,
                    currency: $currency,
                    completedAt: $refund->completedAt,
                );

                $this->reverseCommission->execute(
                    $commission,
                    $allocatedRefund,
                    cumulativeRefundedGrossMinor: $refundedByItem[$bookingItemId],
                );
            }
        }
    }

    /**
     * @param array<int, int> $remainingByItem
     * @return array<int, int>
     */
    private function allocateToItem(int $bookingItemId, int $refundMinor, array $remainingByItem, string $currency): array
    {
        $remaining = $remainingByItem[$bookingItemId] ?? 0;

        if ($refundMinor <= 0 || $remaining <= 0) {
            return [];
        }

        $refund = Money::ofMinor($refundMinor, $currency);
        $capacity = Money::ofMinor($remaining, $currency);
        $allocated = $refund->isGreaterThan($capacity) ? $capacity : $refund;

        return [$bookingItemId => $allocated->getMinorAmount()->toInt()];
    }

    /**
     * @param array<int, int> $remainingByItem
     * @return array<int, int>
     */
    private function allocateAcrossRemainingItems(int $refundMinor, array $remainingByItem, string $currency): array
    {
        if ($refundMinor <= 0) {
            return [];
        }

        $remainingTotal = Money::ofMinor(0, $currency);

        foreach ($remainingByItem as $remainingMinor) {
            $remainingTotal = $remainingTotal->plus(Money::ofMinor($remainingMinor, $currency));
        }

        if ($remainingTotal->isZero()) {
            return [];
        }

        $requested = Money::ofMinor($refundMinor, $currency);
        $allocatable = $requested->isGreaterThan($remainingTotal) ? $remainingTotal : $requested;
        $allocatableMinor = $allocatable->getMinorAmount()->toInt();

        if ($allocatable->isEqualTo($remainingTotal)) {
            return array_filter($remainingByItem, static fn (int $remaining): bool => $remaining > 0);
        }

        $remainingTotalMinor = $remainingTotal->getMinorAmount()->toInt();
        $allocations = [];
        $remainders = [];
        $allocatedTotal = Money::ofMinor(0, $currency);

        foreach ($remainingByItem as $bookingItemId => $remainingMinor) {
            if ($remainingMinor <= 0) {
                continue;
            }

            $numerator = BigDecimal::of($allocatableMinor)->multipliedBy($remainingMinor);
            $allocatedMinor = $numerator
                ->dividedBy($remainingTotalMinor, 0, RoundingMode::DOWN)
                ->toInt();
            $allocations[$bookingItemId] = $allocatedMinor;
            $allocatedTotal = $allocatedTotal->plus(Money::ofMinor($allocatedMinor, $currency));
            $remainders[$bookingItemId] = $numerator->minus(
                BigDecimal::of($allocatedMinor)->multipliedBy($remainingTotalMinor),
            );
        }

        $leftover = $allocatable->minus($allocatedTotal)->getMinorAmount()->toInt();

        if ($leftover > 0) {
            $bookingItemIds = array_keys($remainders);
            usort($bookingItemIds, static function (int $left, int $right) use ($remainders): int {
                $remainderOrder = $remainders[$right]->compareTo($remainders[$left]);

                return $remainderOrder !== 0 ? $remainderOrder : $left <=> $right;
            });

            foreach ($bookingItemIds as $bookingItemId) {
                if ($leftover === 0) {
                    break;
                }

                if ($allocations[$bookingItemId] >= $remainingByItem[$bookingItemId]) {
                    continue;
                }

                $allocations[$bookingItemId] = Money::ofMinor($allocations[$bookingItemId], $currency)
                    ->plus(Money::ofMinor(1, $currency))
                    ->getMinorAmount()
                    ->toInt();
                $leftover = Money::ofMinor($leftover, $currency)
                    ->minus(Money::ofMinor(1, $currency))
                    ->getMinorAmount()
                    ->toInt();
            }
        }

        return array_filter($allocations, static fn (int $allocation): bool => $allocation > 0);
    }
}
