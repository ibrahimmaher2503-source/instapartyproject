<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Contracts\RefundConfirmationGateway;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Events\RefundCompleted;
use App\Modules\Payments\Domain\Events\RefundFailed;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Domain\States\PaymentStatus\PartiallyRefundedState;
use App\Modules\Payments\Domain\States\PaymentStatus\RefundedState;
use App\Modules\Payments\Infrastructure\Repositories\EloquentRefundRepository;
use App\Modules\Settlement\Application\DTOs\LedgerEntryInput;
use App\Modules\Settlement\Application\DTOs\PostLedgerTransactionInput;
use App\Modules\Settlement\Domain\Contracts\LedgerWriter;
use App\Modules\Settlement\Domain\Enums\LedgerDirection;
use App\Modules\Settlement\Domain\Enums\LedgerEntryType;
use App\Modules\Settlement\Domain\Enums\SuspenseAccount;
use App\Modules\Settlement\Domain\Enums\TransactionKind;
use Brick\Money\Money;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProcessRefundAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly EloquentRefundRepository $refunds,
        private readonly LedgerWriter $ledgerWriter,
    ) {}

    public function execute(int $refundId): void
    {
        // Resolve the immutable parent FK without locking the refund first; every
        // refund processor must acquire the payment aggregate lock before its child.
        $paymentId = (int) Refund::query()->findOrFail($refundId)->payment_id;

        DB::transaction(function () use ($refundId, $paymentId): void {
            $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);
            $refund = Refund::query()
                ->whereKey($refundId)
                ->where('payment_id', $payment->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($refund->status === RefundStatus::Completed) {
                return;
            }

            $this->refunds->markProcessing($refund);

            $result = $this->gateway->refund($payment, Money::ofMinor($refund->amount_minor, $refund->amount_currency));

            if ($result->success) {
                $this->completeRefund($payment, $refund, (string) $result->gatewayRef);

                return;
            }

            $this->refunds->markFailed($refund, (string) $result->failureMessage);
            DB::afterCommit(fn () => event(new RefundFailed(
                $refund->id,
                $payment->id,
                ['en' => (string) $result->failureMessage, 'ar' => (string) $result->failureMessage],
            )));
        });
    }

    public function confirmFromWebhook(int $paymentId, string $callbackTransactionId): bool
    {
        return DB::transaction(function () use ($paymentId, $callbackTransactionId): bool {
            $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);
            $paymentCurrency = (string) $payment->amount_currency;
            $refunds = Refund::query()
                ->where('payment_id', $payment->id)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            $completedRefunds = $refunds->filter(fn (Refund $refund): bool => $refund->status === RefundStatus::Completed);
            $unresolvedRefunds = $refunds->reject(fn (Refund $refund): bool => $refund->status === RefundStatus::Completed);

            if ($unresolvedRefunds->count() > 1) {
                return false;
            }

            $completedRefundTotal = Money::ofMinor(0, $paymentCurrency);
            foreach ($completedRefunds as $completedRefund) {
                if ($completedRefund->amount_currency !== $paymentCurrency) {
                    return false;
                }

                $completedRefundTotal = $completedRefundTotal->plus(
                    Money::ofMinor((int) $completedRefund->amount_minor, $paymentCurrency),
                );
            }

            $refund = $unresolvedRefunds->first();
            if ($refund === null) {
                if ($completedRefundTotal->isZero()) {
                    return false;
                }

                $refundAmount = Money::ofMinor(0, $paymentCurrency);
                $expectedCumulativeAmount = $completedRefundTotal;
            } else {
                if ($refund->amount_currency !== $paymentCurrency || (int) $refund->amount_minor <= 0) {
                    return false;
                }

                $refundAmount = Money::ofMinor((int) $refund->amount_minor, $paymentCurrency);
                $expectedCumulativeAmount = $completedRefundTotal->plus($refundAmount);
            }

            $capturedAmount = Money::ofMinor((int) $payment->amount_minor, $paymentCurrency);
            if ($expectedCumulativeAmount->isGreaterThan($capturedAmount)) {
                return false;
            }

            if ($refund === null) {
                if ($completedRefunds->contains(fn (Refund $completed): bool => $completed->gateway_ref === $callbackTransactionId)) {
                    return true;
                }
            }

            if (! ($this->gateway instanceof RefundConfirmationGateway)
                || ! $this->gateway->confirmRefund($payment, $callbackTransactionId, $refundAmount, $expectedCumulativeAmount)) {
                return false;
            }

            if ($refund !== null) {
                $this->completeRefund($payment, $refund, $refund->gateway_ref ?? $callbackTransactionId);
            }

            return true;
        });
    }

    private function completeRefund(Payment $payment, Refund $refund, string $gatewayRef): void
    {
        $this->refunds->markCompleted($refund, $gatewayRef, now());

        $completedRefunds = Refund::query()
            ->where('payment_id', $payment->id)
            ->where('status', RefundStatus::Completed->value)
            ->orderBy('id')
            ->lockForUpdate()
            ->get(['amount_minor']);

        $completedRefundTotal = Money::ofMinor(0, (string) $payment->amount_currency);
        foreach ($completedRefunds as $completedRefund) {
            $completedRefundTotal = $completedRefundTotal->plus(
                Money::ofMinor((int) $completedRefund->amount_minor, (string) $payment->amount_currency),
            );
        }

        $capturedTotal = Money::ofMinor((int) $payment->amount_minor, (string) $payment->amount_currency);
        $payment->update([
            'status' => $completedRefundTotal->isGreaterThanOrEqualTo($capturedTotal)
                ? RefundedState::class
                : PartiallyRefundedState::class,
        ]);

        $correlationId = (string) (Context::get('correlation_id') ?? Str::ulid());

        // Post refund ledger group: debit platform_clearing → credit platform_refund_payable
        $ledgerResult = $this->ledgerWriter->post(new PostLedgerTransactionInput(
            kind: TransactionKind::Refund,
            currency: (string) $refund->amount_currency,
            idempotencyKey: "refund:{$refund->id}",
            correlationId: $correlationId,
            causationId: null,
            initiatorType: 'system',
            initiatedByUserId: null,
            entries: [
                new LedgerEntryInput(
                    walletOwnerType: 'platform_account',
                    walletOwnerId: SuspenseAccount::PlatformClearing->value,
                    direction: LedgerDirection::Debit,
                    amountMinor: (int) $refund->amount_minor,
                    entryType: LedgerEntryType::RefundDebitPlatform,
                    counterAccountType: 'platform_account',
                    counterAccountId: SuspenseAccount::PlatformRefundPayable->value,
                    relatedEntityType: 'refund',
                    relatedEntityId: $refund->id,
                ),
                new LedgerEntryInput(
                    walletOwnerType: 'platform_account',
                    walletOwnerId: SuspenseAccount::PlatformRefundPayable->value,
                    direction: LedgerDirection::Credit,
                    amountMinor: (int) $refund->amount_minor,
                    entryType: LedgerEntryType::RefundCreditCustomer,
                    counterAccountType: 'platform_account',
                    counterAccountId: SuspenseAccount::PlatformClearing->value,
                    relatedEntityType: 'refund',
                    relatedEntityId: $refund->id,
                ),
            ],
            descriptionKey: 'settlement.ledger.refund',
            descriptionParams: ['refund_id' => $refund->public_id],
        ));

        // Link the ledger group to the refund row
        $refund->update(['ledger_group_id' => $ledgerResult->groupId]);

        DB::afterCommit(fn () => event(new RefundCompleted(
            $refund->id,
            $payment->id,
            $payment->booking_id,
            (int) $refund->amount_minor,
            (string) $refund->amount_currency,
            $refund->reason_code->value,
        )));
    }
}
