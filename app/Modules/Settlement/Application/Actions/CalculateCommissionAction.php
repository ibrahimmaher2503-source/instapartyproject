<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Actions;

use App\Modules\Settlement\Application\DTOs\BookingItemSnapshotDto;
use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PaymentSnapshotDto;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Domain\Contracts\BookingPayoutWriter;
use App\Modules\Settlement\Domain\Contracts\CommissionRateResolver;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Enums\CommissionStatus;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use App\Modules\Settlement\Domain\Events\CommissionCalculated;
use App\Modules\Settlement\Domain\Models\Commission;
use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CalculateCommissionAction
{
    private const VENDOR_OWNER_TYPE = 'App\\Modules\\Identity\\Domain\\Models\\VendorProfile';

    public function __construct(
        private readonly CommissionRateResolver $rateResolver,
        private readonly LedgerWriter $ledgerWriter,
        private readonly BookingPayoutWriter $bookingPayoutWriter,
    ) {}

    public function execute(BookingItemSnapshotDto $item, PaymentSnapshotDto $payment): Commission
    {
        return DB::transaction(function () use ($item, $payment) {
            // Idempotency: return existing commission if already calculated for this booking item
            $existing = Commission::where('booking_item_id', $item->id)->first();
            if ($existing !== null) {
                $this->recordBookingPayout($item->id, (int) $existing->commission_minor, $existing->commission_currency, $payment->id);
                $this->creditDelivery($item);

                return $existing;
            }

            $hasSnapshotBps = $item->commissionBps !== null;
            $bps = $item->commissionBps
                ?? $this->rateResolver->resolve($item->categoryId, $item->productType)
                ?? 0;

            if ($bps === 0 && ! $hasSnapshotBps) {
                Log::warning('Settlement: no commission rate found, defaulting to 0', [
                    'category_id' => $item->categoryId,
                    'product_type' => $item->productType->value,
                    'booking_item_id' => $item->id,
                ]);
            }

            $gross = Money::ofMinor($item->totalMinor, $item->totalCurrency);
            $commission = $gross->multipliedBy($bps)->dividedBy(10000, RoundingMode::HALF_EVEN);
            $commissionMinor = $commission->getMinorAmount()->toInt();
            $vendorShareMinor = $gross->minus($commission)->getMinorAmount()->toInt();

            $idempotencyKey = "comm:{$item->id}";
            $correlationId = (string) Str::ulid();

            /** @var Commission $commission */
            $commission = Commission::create([
                'booking_item_id' => $item->id,
                'payment_id' => $payment->id,
                'vendor_profile_id' => $item->vendorProfileId,
                'category_id' => $item->categoryId,
                'product_type' => $item->productType,
                'gross_amount_minor' => $item->totalMinor,
                'gross_amount_currency' => $item->totalCurrency,
                'commission_bps' => $bps,
                'commission_minor' => $commissionMinor,
                'commission_currency' => $item->totalCurrency,
                'vendor_share_minor' => $vendorShareMinor,
                'vendor_share_currency' => $item->totalCurrency,
                'status' => CommissionStatus::Calculated->value,
                'idempotency_key' => $idempotencyKey,
            ]);

            // Post commission_accrual group:
            //   Debit:  platform_clearing (total gross amount leaves clearing)
            //   Credit: vendor wallet     (vendor's share)
            //   Credit: platform commission receivable (platform's cut)
            $entries = [
                new LedgerEntryInput(
                    walletOwnerType: 'platform_account',
                    walletOwnerId: SuspenseAccount::PlatformClearing->value,
                    direction: LedgerDirection::Debit,
                    amountMinor: $item->totalMinor,
                    entryType: LedgerEntryType::CommissionAccrual,
                    counterAccountType: self::VENDOR_OWNER_TYPE,
                    counterAccountId: $item->vendorProfileId,
                    descriptionKey: 'settlement.commission.platform_clearing_debit',
                    descriptionParams: ['booking_item_id' => $item->id],
                    relatedEntityType: 'commission',
                    relatedEntityId: $commission->id,
                ),
                new LedgerEntryInput(
                    walletOwnerType: self::VENDOR_OWNER_TYPE,
                    walletOwnerId: $item->vendorProfileId,
                    direction: LedgerDirection::Credit,
                    amountMinor: $vendorShareMinor,
                    entryType: LedgerEntryType::CommissionAccrual,
                    counterAccountType: 'platform_account',
                    counterAccountId: SuspenseAccount::PlatformClearing->value,
                    descriptionKey: 'settlement.commission.vendor_credit',
                    descriptionParams: ['booking_item_id' => $item->id, 'commission_id' => $commission->id],
                    relatedEntityType: 'commission',
                    relatedEntityId: $commission->id,
                ),
            ];

            // Only add the platform commission entry if there is actually a commission
            if ($commissionMinor > 0) {
                $entries[] = new LedgerEntryInput(
                    walletOwnerType: 'platform_account',
                    walletOwnerId: SuspenseAccount::PlatformCommissionReceivable->value,
                    direction: LedgerDirection::Credit,
                    amountMinor: $commissionMinor,
                    entryType: LedgerEntryType::CommissionAccrual,
                    counterAccountType: 'platform_account',
                    counterAccountId: SuspenseAccount::PlatformClearing->value,
                    descriptionKey: 'settlement.commission.platform_commission_credit',
                    descriptionParams: ['booking_item_id' => $item->id],
                    relatedEntityType: 'commission',
                    relatedEntityId: $commission->id,
                );
            }

            $result = $this->ledgerWriter->post(new PostLedgerTransactionInput(
                kind: TransactionKind::CommissionAccrual,
                currency: $item->totalCurrency,
                idempotencyKey: $idempotencyKey,
                correlationId: $correlationId,
                causationId: null,
                initiatorType: 'system',
                initiatedByUserId: null,
                entries: $entries,
                descriptionKey: 'settlement.commission.accrual_group',
                descriptionParams: ['booking_item_id' => $item->id],
                metadata: ['commission_id' => $commission->id],
            ));

            // Link the vendor credit entry as the accrual reference
            // Use wallet_id lookup (not JSON_EXTRACT) to stay SQLite-compatible in tests
            $vendorWalletId = DB::table('wallets')
                ->where('owner_type', self::VENDOR_OWNER_TYPE)
                ->where('owner_id', $item->vendorProfileId)
                ->value('id');

            $vendorLedgerEntryId = $vendorWalletId !== null
                ? DB::table('wallet_ledger')
                    ->where('transaction_group_id', $result->groupId)
                    ->where('wallet_id', $vendorWalletId)
                    ->where('direction', 'credit')
                    ->value('id')
                : null;

            if ($vendorLedgerEntryId !== null) {
                $commission->update(['accrual_ledger_entry_id' => $vendorLedgerEntryId]);
            }

            $this->recordBookingPayout($item->id, $commissionMinor, $item->totalCurrency, $payment->id);
            $this->creditDelivery($item);

            DB::afterCommit(fn () => event(new CommissionCalculated(
                commissionId: $commission->id,
                vendorProfileId: $item->vendorProfileId,
                vendorShareMinor: $vendorShareMinor,
                currency: $item->totalCurrency,
            )));

            return $commission;
        });
    }

    private function creditDelivery(BookingItemSnapshotDto $item): void
    {
        if ($item->bookingVendorId === null || $item->deliveryFeeMinor === 0) {
            return;
        }
        abort_unless($item->deliveryFeeCurrency === $item->totalCurrency && $item->deliveryFeeMinor > 0, 409);
        $this->ledgerWriter->post(new PostLedgerTransactionInput(
            kind: TransactionKind::CommissionAccrual,
            currency: $item->totalCurrency,
            idempotencyKey: 'vendor_delivery:'.$item->bookingVendorId,
            correlationId: (string) Str::ulid(),
            causationId: null,
            initiatorType: 'system',
            initiatedByUserId: null,
            entries: [
                new LedgerEntryInput(
                    walletOwnerType: 'platform_account', walletOwnerId: SuspenseAccount::PlatformClearing->value,
                    direction: LedgerDirection::Debit, amountMinor: $item->deliveryFeeMinor,
                    entryType: LedgerEntryType::VendorCredit, counterAccountType: self::VENDOR_OWNER_TYPE,
                    counterAccountId: $item->vendorProfileId, relatedEntityType: 'booking_vendor', relatedEntityId: $item->bookingVendorId,
                ),
                new LedgerEntryInput(
                    walletOwnerType: self::VENDOR_OWNER_TYPE, walletOwnerId: $item->vendorProfileId,
                    direction: LedgerDirection::Credit, amountMinor: $item->deliveryFeeMinor,
                    entryType: LedgerEntryType::VendorCredit, counterAccountType: 'platform_account',
                    counterAccountId: SuspenseAccount::PlatformClearing->value, relatedEntityType: 'booking_vendor', relatedEntityId: $item->bookingVendorId,
                ),
            ],
            descriptionKey: 'settlement.delivery_accrual',
            descriptionParams: [],
        ));
    }

    private function recordBookingPayout(int $itemId, int $minor, string $currency, int $paymentId): void
    {
        $persisted = Commission::query()->where('payment_id', $paymentId)->where('commission_currency', $currency)
            ->pluck('commission_minor', 'booking_item_id')->map(fn ($value): int => (int) $value)->all();
        $this->bookingPayoutWriter->recordCommission($itemId, $minor, $currency, $persisted);
    }
}
