<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Application\DTOs\BookingItemSnapshotDto;
use App\Modules\Settlement\Application\DTOs\RefundSnapshotDto;
use App\Modules\Settlement\Domain\Contracts\SettlementBookingReader;
use App\Modules\Settlement\Domain\Contracts\SettlementPaymentReader;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Infrastructure\Repositories\EloquentCommissionRepository;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
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
        DB::transaction(function () use ($paymentId): void {
            if (! $this->paymentReader->lockForRefundReconciliation($paymentId)) {
                Log::error('Settlement: cannot reconcile completed refunds for missing payment', [
                    'payment_id' => $paymentId,
                ]);

                return;
            }

            $this->reconcileLockedPayment($paymentId);
        });
    }

    private function reconcileLockedPayment(int $paymentId): void
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
        $commissionByItem = [];
        $remainingByItem = [];
        $refundedByItem = [];
        $deliveryByBookingVendor = [];
        $remainingServiceGross = Money::ofMinor(0, $currency);
        $actualServiceCashReversed = Money::ofMinor(0, $currency);

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

            if ($item->deliveryFeeMinor < 0 || ($item->deliveryFeeMinor > 0 && (
                $item->bookingVendorId === null
                || $item->deliveryFeeCurrency !== $currency
            ))) {
                Log::error('Settlement: invalid vendor delivery snapshot while reconciling refunds', [
                    'payment_id' => $paymentId,
                    'booking_item_id' => $item->id,
                    'booking_vendor_id' => $item->bookingVendorId,
                    'delivery_fee_minor' => $item->deliveryFeeMinor,
                    'delivery_fee_currency' => $item->deliveryFeeCurrency,
                    'payment_currency' => $currency,
                ]);

                return;
            }

            if ($item->bookingVendorId !== null) {
                $bookingVendorId = $item->bookingVendorId;
                if (array_key_exists($bookingVendorId, $deliveryByBookingVendor)
                    && $deliveryByBookingVendor[$bookingVendorId] !== $item->deliveryFeeMinor) {
                    Log::error('Settlement: inconsistent vendor delivery snapshots while reconciling refunds', [
                        'payment_id' => $paymentId,
                        'booking_item_id' => $item->id,
                        'booking_vendor_id' => $bookingVendorId,
                    ]);

                    return;
                }

                $deliveryByBookingVendor[$bookingVendorId] = $item->deliveryFeeMinor;
            }

            $itemGross = Money::ofMinor($item->totalMinor, $currency);
            $commission = $this->commissionRepo->findByBookingItemId($item->id);
            $commissionByItem[$item->id] = $commission;
            $actualReversedForItem = $commission === null
                ? Money::ofMinor(0, $currency)
                : Money::ofMinor($this->actualReversedForCommission($commission->id, $currency), $currency);
            $actualServiceCashReversed = $actualServiceCashReversed->plus($actualReversedForItem);
            $actualReversed = Money::min($itemGross, $actualReversedForItem);
            $remainingByItem[$item->id] = $itemGross->minus($actualReversed)->getMinorAmount()->toInt();
            $refundedByItem[$item->id] = $actualReversed->getMinorAmount()->toInt();
            $remainingServiceGross = $remainingServiceGross->plus($itemGross->minus($actualReversed));
        }

        $vendorDeliveryGross = $this->sumMinorAmounts($deliveryByBookingVendor, $currency);
        $paymentCash = Money::ofMinor($payment->amountMinor, $currency);

        if ($paymentCash->isLessThan($vendorDeliveryGross)) {
            Log::error('Settlement: vendor delivery exceeds captured payment while reconciling refunds', [
                'payment_id' => $paymentId,
                'payment_minor' => $payment->amountMinor,
                'vendor_delivery_minor' => $vendorDeliveryGross->getMinorAmount()->toInt(),
                'currency' => $currency,
            ]);

            return;
        }

        $serviceCashBudget = $paymentCash->minus($vendorDeliveryGross);
        $remainingServiceCash = $serviceCashBudget->isGreaterThan($actualServiceCashReversed)
            ? $serviceCashBudget->minus($actualServiceCashReversed)
            : Money::ofMinor(0, $currency);

        if ($actualServiceCashReversed->isGreaterThan($serviceCashBudget)) {
            Log::warning('Settlement: existing commission reversals exceed service cash budget; preserving ledger history', [
                'payment_id' => $paymentId,
                'service_cash_budget_minor' => $serviceCashBudget->getMinorAmount()->toInt(),
                'actual_reversed_minor' => $actualServiceCashReversed->getMinorAmount()->toInt(),
                'currency' => $currency,
            ]);
        }

        if ($remainingServiceGross->isZero() || $remainingServiceCash->isZero()) {
            return;
        }

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

            $actualReversalsForRefund = $this->actualReversalsForRefund($refund->id, $currency);
            $actualRefundGross = $this->sumMinorAmounts($actualReversalsForRefund, $currency);
            $refundGross = Money::ofMinor($refund->amountMinor, $currency);
            $remainingRefundBudget = $refundGross->isGreaterThan($actualRefundGross)
                ? $refundGross->minus($actualRefundGross)
                : Money::ofMinor(0, $currency);
            $remainingRefundBudget = Money::min($remainingRefundBudget, $remainingServiceCash);
            $remainingRefundBudgetMinor = $remainingRefundBudget->getMinorAmount()->toInt();
            $allocations = $refund->bookingItemId === null
                ? $this->allocateAcrossRemainingItems($remainingRefundBudgetMinor, $remainingByItem, $currency)
                : $this->allocateToItem($refund->bookingItemId, $remainingRefundBudgetMinor, $remainingByItem, $currency);
            $remainingServiceCash = $remainingServiceCash->minus($this->sumMinorAmounts($allocations, $currency));

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

                $commission = $commissionByItem[$bookingItemId] ?? null;

                if ($commission === null) {
                    Log::warning('Settlement: commission is not accrued yet for a completed refund allocation', [
                        'payment_id' => $paymentId,
                        'refund_id' => $refund->id,
                        'booking_item_id' => $bookingItemId,
                    ]);

                    continue;
                }

                $actualCommissionReversalMinor = $this->actualReversedForCommission($commission->id, $currency);
                $actualCommissionReversal = Money::ofMinor($actualCommissionReversalMinor, $currency);
                $cumulativeRefundTarget = Money::min(
                    Money::min(
                        Money::ofMinor($refundedByItem[$bookingItemId], $currency),
                        $actualCommissionReversal->plus(Money::ofMinor($allocatedMinor, $currency)),
                    ),
                    Money::ofMinor((int) $commission->gross_amount_minor, $currency),
                );
                $cumulativeRefundTargetMinor = $cumulativeRefundTarget->getMinorAmount()->toInt();

                if (! $cumulativeRefundTarget->isGreaterThan($actualCommissionReversal)) {
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
                    idempotencyKey: "comm_rev:{$commission->id}:refund:{$refund->id}:target:{$cumulativeRefundTargetMinor}",
                    cumulativeRefundedGrossMinor: $cumulativeRefundTargetMinor,
                );

            }
        }
    }

    /**
     * @return array<int, int>
     */
    private function actualReversalsForRefund(int $refundId, string $currency): array
    {
        return DB::table('wallet_ledger')
            ->join('wallets', 'wallets.id', '=', 'wallet_ledger.wallet_id')
            ->join('ledger_transaction_groups', 'ledger_transaction_groups.id', '=', 'wallet_ledger.transaction_group_id')
            ->where('wallets.owner_type', 'platform_account')
            ->where('wallets.owner_id', SuspenseAccount::PlatformClearing->value)
            ->where('wallet_ledger.direction', LedgerDirection::Credit->value)
            ->where('wallet_ledger.entry_type', LedgerEntryType::CommissionReversal->value)
            ->where('wallet_ledger.related_entity_type', 'commission')
            ->where('wallet_ledger.currency', $currency)
            ->where('ledger_transaction_groups.kind', TransactionKind::CommissionReversal->value)
            ->where('ledger_transaction_groups.metadata->refund_id', $refundId)
            ->groupBy('wallet_ledger.related_entity_id')
            ->select('wallet_ledger.related_entity_id')
            ->selectRaw('SUM(wallet_ledger.amount_minor) as reversed_minor')
            ->get()
            ->mapWithKeys(static fn (object $row): array => [(int) $row->related_entity_id => (int) $row->reversed_minor])
            ->all();
    }

    private function actualReversedForCommission(int $commissionId, string $currency): int
    {
        return (int) DB::table('wallet_ledger')
            ->join('wallets', 'wallets.id', '=', 'wallet_ledger.wallet_id')
            ->join('ledger_transaction_groups', 'ledger_transaction_groups.id', '=', 'wallet_ledger.transaction_group_id')
            ->where('wallets.owner_type', 'platform_account')
            ->where('wallets.owner_id', SuspenseAccount::PlatformClearing->value)
            ->where('wallet_ledger.direction', LedgerDirection::Credit->value)
            ->where('wallet_ledger.entry_type', LedgerEntryType::CommissionReversal->value)
            ->where('wallet_ledger.related_entity_type', 'commission')
            ->where('wallet_ledger.related_entity_id', $commissionId)
            ->where('wallet_ledger.currency', $currency)
            ->where('ledger_transaction_groups.kind', TransactionKind::CommissionReversal->value)
            ->sum('wallet_ledger.amount_minor');
    }

    /**
     * @param array<int, int> $amountsById
     */
    private function sumMinorAmounts(array $amountsById, string $currency): Money
    {
        $total = Money::ofMinor(0, $currency);

        foreach ($amountsById as $amountMinor) {
            $total = $total->plus(Money::ofMinor($amountMinor, $currency));
        }

        return $total;
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
