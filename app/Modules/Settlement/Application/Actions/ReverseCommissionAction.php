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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
    ): Commission {
        return DB::transaction(function () use ($commission, $refund, $idempotencyKey, $correlationId, $causationId) {
            $commission = Commission::query()->lockForUpdate()->findOrFail($commission->id);
            $refundId = is_int($refund) ? $refund : $refund->id;
            $refundAmount = is_int($refund) ? $commission->gross_amount_minor : $refund->amountMinor;
            $ikey = $idempotencyKey ?? "comm_rev:{$commission->id}:{$refundId}";

            if (LedgerTransactionGroup::query()->where('idempotency_key', $ikey)->exists()) {
                return $commission;
            }

            $refundAmount = min($refundAmount, $commission->gross_amount_minor);

            $proportionDecimal = BigDecimal::of($refundAmount)
                ->dividedBy($commission->gross_amount_minor, 10, RoundingMode::HALF_EVEN);

            $reversalMinor = min(
                $commission->vendor_share_minor - $commission->reversed_amount_minor,
                (int) BigDecimal::of($commission->vendor_share_minor)
                    ->multipliedBy($proportionDecimal)
                    ->toScale(0, RoundingMode::HALF_EVEN)
                    ->toInt(),
            );

            $commissionReversalMinor = (int) BigDecimal::of($commission->commission_minor)
                ->multipliedBy($proportionDecimal)
                ->toScale(0, RoundingMode::HALF_EVEN)
                ->toInt();

            $newReversed = $commission->reversed_amount_minor + $reversalMinor;
            $isFullReversal = $newReversed >= $commission->vendor_share_minor;

            $commission->update([
                'reversed_amount_minor' => $newReversed,
                'status' => $isFullReversal
                    ? ReversedState::class
                    : PartiallyReversedState::class,
            ]);

            // Commission reversal group:
            //   Debit:  vendor wallet (clawback vendor's share)
            //   Debit:  platform_commission_receivable (clawback platform's cut)
            //   Credit: platform_clearing (the gross amount returns to clearing)
            $grossReversalMinor = $reversalMinor + $commissionReversalMinor;

            $entries = [
                new LedgerEntryInput(
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
                ),
                new LedgerEntryInput(
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
                ),
            ];

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
}
