<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Payments\Application\DTOs\InitiateRefundDto;
use App\Modules\Payments\Application\Services\RefundPolicyService;
use App\Modules\Payments\Domain\Contracts\PaymentsBookingReader;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Exceptions\PartialRefundUnsupportedException;
use App\Modules\Payments\Domain\Exceptions\RefundPolicyViolationException;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Infrastructure\Repositories\EloquentPaymentRepository;
use App\Modules\Payments\Infrastructure\Repositories\EloquentRefundRepository;
use App\Modules\Settlement\Domain\Exceptions\OverRefundAttemptedException;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;

class InitiateRefundAction
{
    public function __construct(
        private readonly EloquentPaymentRepository $payments,
        private readonly EloquentRefundRepository $refunds,
        private readonly RefundPolicyService $policyService,
        private readonly PaymentsBookingReader $bookingReader,
        private readonly ProcessRefundAction $processRefund,
    ) {}

    public function execute(InitiateRefundDto $dto): Refund
    {
        [$refund, $shouldProcess] = DB::transaction(function () use ($dto) {
            // Current callers enter outside an outer transaction; acquire the
            // payment lock before establishing the reservation read snapshot.
            $payment = $this->payments->findByIdForUpdate($dto->paymentId)
                ?? abort(404, 'Payment not found');

            $requestedAmount = $dto->requestedAmountMinor ?? (int) $payment->amount_minor;
            $currency = (string) $payment->amount_currency;

            if ($dto->idempotencyKey !== null) {
                $idempotencyKeyPrefix = substr($dto->idempotencyKey, 0, 64);
                $existingRefund = Refund::query()
                    ->where('idempotency_key', 'like', $idempotencyKeyPrefix.'%')
                    ->first();

                if ($existingRefund !== null) {
                    $sameRequest = $existingRefund->idempotency_key === $dto->idempotencyKey
                        && (int) $existingRefund->payment_id === (int) $payment->id
                        && (int) $existingRefund->booking_id === $dto->bookingId
                        && (int) $existingRefund->initiated_by === $dto->initiatedBy
                        && (int) $existingRefund->amount_minor === $requestedAmount
                        && (string) $existingRefund->amount_currency === $currency
                        && $existingRefund->reason_code === $dto->reasonCode
                        && (
                            $existingRefund->status === RefundStatus::Failed
                            || $existingRefund->reason_notes == $dto->reasonNotes
                        );

                    if (! $sameRequest) {
                        abort(409, 'Idempotency key conflict');
                    }

                    // The prior call may have reached the gateway before throwing;
                    // replay its persisted state without issuing another refund.
                    return [$existingRefund, false];
                }
            }

            if ($dto->requestedAmountMinor !== null && $dto->requestedAmountMinor !== (int) $payment->amount_minor) {
                throw new PartialRefundUnsupportedException;
            }

            // The locked payment serializes reservation creation. Count every
            // non-failed refund, including pending and processing reservations;
            // keep this a plain read to avoid reversing the processor's lock order.
            $priorRefundedMinor = (int) Refund::query()
                ->where('payment_id', $payment->id)
                ->where('status', '!=', RefundStatus::Failed->value)
                ->sum('amount_minor');

            $capturedTotal = (int) $payment->amount_minor;
            $reservedTotal = Money::ofMinor($priorRefundedMinor, $currency)
                ->plus(Money::ofMinor($requestedAmount, $currency));
            $capturedAmount = Money::ofMinor($capturedTotal, $currency);

            if ($reservedTotal->isGreaterThan($capturedAmount)) {
                throw new OverRefundAttemptedException(
                    paymentId: $payment->id,
                    capturedTotal: $capturedTotal,
                    alreadyRefunded: $priorRefundedMinor,
                    requested: $requestedAmount,
                );
            }

            $items = $this->bookingReader->itemsFor($dto->bookingId);
            foreach ($items as $item) {
                $policy = $this->policyService->policyFor(
                    ProductType::from($item->productType->value),
                    $item->itemStatus,
                    $item->eventStartsAt,
                    $item->serviceId,
                );

                if (! $policy->allowed) {
                    throw new RefundPolicyViolationException($policy->reasonCode, $policy->reasonMessageKey);
                }
            }

            return [$this->refunds->create($dto, $requestedAmount, $currency, $dto->idempotencyKey), true];
        });

        if ($shouldProcess) {
            // Commit the reservation and release the payment lock before calling the gateway.
            $this->processRefund->execute($refund->id);
        }

        return $refund->fresh() ?? $refund;
    }
}
