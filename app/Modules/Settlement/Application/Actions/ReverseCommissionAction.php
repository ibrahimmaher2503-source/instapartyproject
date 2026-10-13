<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Application\DTOs\RefundSnapshotDto;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Events\CommissionReversed;
use App\Modules\Settlement\Domain\Models\Commission;
use App\Modules\Settlement\Domain\Models\LedgerTransactionGroup;
use App\Modules\Settlement\Domain\States\CommissionStatus\PartiallyReversedState;
use App\Modules\Settlement\Domain\States\CommissionStatus\ReversedState;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ReverseCommissionAction
{
    private const VENDOR_OWNER_TYPE = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';

    public function __construct(
        private readonly LedgerWriter $ledgerWriter,
    ) {}

    public function execute(
        Commission $commission,
        RefundSnapshotDto|int $refund,
        ?string $idempotencyKey = null,
        ?string $correlationId = null,
        ?string $causationId = null,
        ?int $cumulativeRefundedGrossMinor = null,
    ): Commission {
        return DB::transaction(function () use ($commission, $refund, $idempotencyKey, $correlationId, $causationId, $cumulativeRefundedGrossMinor) {
            $commission = Commission::query()->lockForUpdate()->findOrFail($commission->id);
            $refundId = is_int($refund) ? $refund : $refund->id;
            $refundAmount = is_int($refund) ? $commission->gross_amount_minor : $refund->amountMinor;
            $ikey = $idempotencyKey ?? "comm_rev:{$commission->id}:{$refundId}";

            if (LedgerTransactionGroup::query()->where('idempotency_key', $ikey)->exists()) {
                return $commission;
            }

            $currency = $commission->gross_amount_currency;

            if ($refund instanceof RefundSnapshotDto && $refund->currency !== $currency) {
                throw new InvalidArgumentException('Refund and commission currencies must match.');
            }

            $grossTotal = Money::ofMinor($commission->gross_amount_minor, $currency);

            if ($grossTotal->isZero()) {
                return $commission;
            }

            $refundAmount = max(0, min($refundAmount, $grossTotal->getMinorAmount()->toInt()));

            if ($refundAmount === 0) {
                return $commission;
            }

            $vendorTotal = Money::ofMinor($commission->vendor_share_minor, $currency);
            $storedVendorReversedMinor = max(0, (int) $commission->reversed_amount_minor);
            $vendorLedgerReversedMinor = (int) DB::table('wallet_ledger')
                ->join('wallets', 'wallets.id', '=', 'wallet_ledger.wallet_id')
                ->whereIn('wallets.owner_type', [self::VENDOR_OWNER_TYPE, 'vendor', 'vendor_profile'])
                ->where('wallets.owner_id', $commission->vendor_profile_id)
                ->where('wallet_ledger.direction', LedgerDirection::Debit->value)
                ->where('wallet_ledger.entry_type', LedgerEntryType::CommissionReversal->value)
                ->where('wallet_ledger.currency', $currency)
                ->whereIn('wallet_ledger.transaction_group_id', DB::table('wallet_ledger')
                    ->where('direction', LedgerDirection::Credit->value)
                    ->where('entry_type', LedgerEntryType::CommissionReversal->value)
                    ->where('related_entity_type', 'commission')
                    ->where('related_entity_id', $commission->id)
                    ->where('currency', $currency)
                    ->select('transaction_group_id'))
                ->sum('wallet_ledger.amount_minor');
            if ($storedVendorReversedMinor > $vendorLedgerReversedMinor) {
                Log::warning('Settlement: vendor reversal projection exceeds traced ledger; preserving history without a new reversal', [
                    'commission_id' => $commission->id,
                ]);

                return $commission;
            }
            $vendorAlreadyReversed = Money::ofMinor(
                min($vendorLedgerReversedMinor, $vendorTotal->getMinorAmount()->toInt()),
                $currency,
            );

            $platformTotal = Money::ofMinor($commission->commission_minor, $currency);
            $platformAlreadyReversedMinor = (int) DB::table('wallet_ledger')
                ->join('wallets', 'wallets.id', '=', 'wallet_ledger.wallet_id')
                ->where('wallets.owner_type', 'platform_account')
                ->where('wallets.owner_id', SuspenseAccount::PlatformCommissionReceivable->value)
                ->where('wallet_ledger.direction', LedgerDirection::Debit->value)
                ->where('wallet_ledger.entry_type', LedgerEntryType::CommissionReversal->value)
                ->where('wallet_ledger.related_entity_type', 'commission')
                ->where('wallet_ledger.related_entity_id', $commission->id)
                ->where('wallet_ledger.currency', $currency)
                ->sum('wallet_ledger.amount_minor');
            $platformAlreadyReversed = Money::ofMinor(
                min(max(0, $platformAlreadyReversedMinor), $platformTotal->getMinorAmount()->toInt()),
                $currency,
            );

            if ($cumulativeRefundedGrossMinor !== null) {
                $cumulativeGrossMinor = max(0, min(
                    $cumulativeRefundedGrossMinor,
                    $grossTotal->getMinorAmount()->toInt(),
                ));
                $platformTarget = $this->cumulativeComponentTarget(
                    $platformTotal,
                    $grossTotal->getMinorAmount()->toInt(),
                    $cumulativeGrossMinor,
                    $currency,
                );
                $vendorTarget = Money::ofMinor($cumulativeGrossMinor, $currency)->minus($platformTarget);
            } else {
                $vendorTarget = $this->incrementalComponentTarget(
                    $vendorTotal,
                    $vendorAlreadyReversed,
                    $refundAmount,
                    $grossTotal->getMinorAmount()->toInt(),
                    $currency,
                );
                $platformTarget = $this->incrementalComponentTarget(
                    $platformTotal,
                    $platformAlreadyReversed,
                    $refundAmount,
                    $grossTotal->getMinorAmount()->toInt(),
                    $currency,
                );
            }

            $vendorRemaining = $vendorTotal->minus($vendorAlreadyReversed);
            $platformRemaining = $platformTotal->minus($platformAlreadyReversed);
            $reversalMinor = Money::min(
                $this->positiveDifference($vendorTarget, $vendorAlreadyReversed, $currency),
                $vendorRemaining,
            )
                ->getMinorAmount()
                ->toInt();
            $commissionReversalMinor = Money::min(
                $this->positiveDifference($platformTarget, $platformAlreadyReversed, $currency),
                $platformRemaining,
            )
                ->getMinorAmount()
                ->toInt();
            $grossReversal = Money::ofMinor($reversalMinor, $currency)
                ->plus(Money::ofMinor($commissionReversalMinor, $currency));

            if ($grossReversal->isZero()) {
                return $commission;
            }

            $newReversedMinor = max(
                $storedVendorReversedMinor,
                $vendorAlreadyReversed->plus(Money::ofMinor($reversalMinor, $currency))->getMinorAmount()->toInt(),
            );
            $platformReversedAfterThisEntry = $platformAlreadyReversed
                ->plus(Money::ofMinor($commissionReversalMinor, $currency));
            $isFullReversal = $newReversedMinor >= $vendorTotal->getMinorAmount()->toInt()
                && ! $platformReversedAfterThisEntry->isLessThan($platformTotal);

            $commission->update([
                'reversed_amount_minor' => $newReversedMinor,
                'status' => $isFullReversal
                    ? ReversedState::class
                    : PartiallyReversedState::class,
            ]);

            // Commission reversal group:
            //   Debit:  vendor wallet (clawback vendor's share)
            //   Debit:  platform_commission_receivable (clawback platform's cut)
            //   Credit: platform_clearing (the gross amount returns to clearing)
            $grossReversalMinor = $grossReversal->getMinorAmount()->toInt();
            $entries = [];

            if ($reversalMinor > 0) {
                $entries[] = new LedgerEntryInput(
                    walletOwnerType: self::VENDOR_OWNER_TYPE,
                    walletOwnerId: $commission->vendor_profile_id,
                    direction: LedgerDirection::Debit,
                    amountMinor: $reversalMinor,
                    entryType: LedgerEntryType::CommissionReversal,
                    counterAccountType: 'platform_account',
                    counterAccountId: SuspenseAccount::PlatformClearing->value,
                    descriptionKey: 'settlement.commission.reversal_vendor_debit',
                    descriptionParams: ['commission_id' => $commission->id],
                    relatedEntityType: 'refund',
                    relatedEntityId: $refundId,
                );
            }

            $entries[] = new LedgerEntryInput(
                walletOwnerType: 'platform_account',
                walletOwnerId: SuspenseAccount::PlatformClearing->value,
                direction: LedgerDirection::Credit,
                amountMinor: $grossReversalMinor,
                entryType: LedgerEntryType::CommissionReversal,
                counterAccountType: self::VENDOR_OWNER_TYPE,
                counterAccountId: $commission->vendor_profile_id,
                descriptionKey: 'settlement.commission.reversal_clearing_credit',
                descriptionParams: ['commission_id' => $commission->id],
                relatedEntityType: 'commission',
                relatedEntityId: $commission->id,
            );

            if ($commissionReversalMinor > 0) {
                $entries[] = new LedgerEntryInput(
                    walletOwnerType: 'platform_account',
                    walletOwnerId: SuspenseAccount::PlatformCommissionReceivable->value,
                    direction: LedgerDirection::Debit,
                    amountMinor: $commissionReversalMinor,
                    entryType: LedgerEntryType::CommissionReversal,
                    counterAccountType: 'platform_account',
                    counterAccountId: SuspenseAccount::PlatformClearing->value,
                    descriptionKey: 'settlement.commission.reversal_platform_debit',
                    descriptionParams: ['commission_id' => $commission->id],
                    relatedEntityType: 'commission',
                    relatedEntityId: $commission->id,
                );
            }

            $result = $this->ledgerWriter->post(new PostLedgerTransactionInput(
                kind: TransactionKind::CommissionReversal,
                currency: $commission->vendor_share_currency,
                idempotencyKey: $ikey,
                correlationId: $correlationId ?? (string) Str::ulid(),
                causationId: $causationId,
                initiatorType: 'system',
                initiatedByUserId: null,
                entries: $entries,
                descriptionKey: 'settlement.commission.reversal_group',
                descriptionParams: ['commission_id' => $commission->id],
                metadata: ['refund_id' => $refundId],
            ));

            // Link the vendor debit entry as the reversal reference
            $vendorLedgerEntryId = DB::table('wallet_ledger')
                ->where('transaction_group_id', $result->groupId)
                ->where('direction', 'debit')
                ->whereRaw('related_entity_type = ? AND related_entity_id = ?', ['refund', $refundId])
                ->value('id');

            if ($vendorLedgerEntryId !== null) {
                $commission->update(['reversal_ledger_entry_id' => $vendorLedgerEntryId]);
            }

            DB::afterCommit(fn () => event(new CommissionReversed(
                commissionId: $commission->id,
                reversedAmountMinor: $reversalMinor,
                currency: $commission->vendor_share_currency,
            )));

            $commission->refresh();

            return $commission;
        });
    }

    private function cumulativeComponentTarget(Money $component, int $grossMinor, int $cumulativeRefundedGrossMinor, string $currency): Money
    {
        if ($component->isZero() || $cumulativeRefundedGrossMinor <= 0) {
            return Money::ofMinor(0, $currency);
        }

        if ($cumulativeRefundedGrossMinor >= $grossMinor) {
            return $component;
        }

        $targetMinor = BigDecimal::of($component->getMinorAmount())
            ->multipliedBy($cumulativeRefundedGrossMinor)
            ->dividedBy($grossMinor, 0, RoundingMode::HALF_EVEN)
            ->toInt();

        return Money::min($component, Money::ofMinor($targetMinor, $currency));
    }

    private function incrementalComponentTarget(Money $component, Money $alreadyReversed, int $refundMinor, int $grossMinor, string $currency): Money
    {
        if ($component->isZero() || $refundMinor <= 0) {
            return $alreadyReversed;
        }

        $incrementMinor = BigDecimal::of($component->getMinorAmount())
            ->multipliedBy($refundMinor)
            ->dividedBy($grossMinor, 0, RoundingMode::HALF_EVEN)
            ->toInt();
        $target = $alreadyReversed->plus(Money::ofMinor($incrementMinor, $currency));

        return Money::min($component, $target);
    }

    private function positiveDifference(Money $target, Money $current, string $currency): Money
    {
        return $target->isGreaterThan($current)
            ? $target->minus($current)
            : Money::ofMinor(0, $currency);
    }
}
