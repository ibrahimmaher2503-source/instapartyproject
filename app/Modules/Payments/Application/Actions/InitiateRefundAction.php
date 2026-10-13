<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Payments\Application\DTOs\InitiateRefundDto;
use App\Modules\Payments\Application\Services\RefundPolicyService;
use App\Modules\Payments\Domain\Contracts\PaymentsBookingReader;
use App\Modules\Payments\Domain\Enums\RefundStatus;
use App\Modules\Payments\Domain\Exceptions\RefundPolicyViolationException;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Payments\Infrastructure\Repositories\EloquentPaymentRepository;
use App\Modules\Payments\Infrastructure\Repositories\EloquentRefundRepository;
use App\Modules\Settlement\Domain\Exceptions\OverRefundAttemptedException;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
                        && ($dto->requestedAmountMinor === null || (int) $existingRefund->amount_minor === $requestedAmount)
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

            abort_unless((int) $payment->booking_id === $dto->bookingId, 409, 'Payment booking mismatch');
            abort_unless(in_array($payment->status->getValue(), ['captured', 'partially_refunded'], true), 409, 'No captured payment to refund');
            // Keep one unknown provider outcome per payment so confirmation can
            // identify it; exact persisted-key replays have already returned above.
            abort_if(Refund::query()->where('payment_id', $payment->id)
                ->whereIn('status', [RefundStatus::Pending->value, RefundStatus::Processing->value])->exists(),
                409, __('payments::refunds.errors.refund_in_progress'));

            // Payment then Booking matches settlement's lock order. Locking reads
            // see reservations committed while this request waited for the booking.
            $bookingBudget = $this->bookingReader->refundCashBudget($dto->bookingId, true)
                ?? abort(404, 'Booking not found');
            abort_unless($bookingBudget->getCurrency()->getCurrencyCode() === $currency, 409, 'Refund currency mismatch');
            $reservations = Refund::query()->where('booking_id', $dto->bookingId)
                ->where('status', '!=', RefundStatus::Failed->value)
                ->orderBy('id')->lockForUpdate()->get();
            $bookingReserved = Money::ofMinor(0, $currency);
            $reservedAmount = Money::ofMinor(0, $currency);
            foreach ($reservations as $reservation) {
                abort_unless((string) $reservation->amount_currency === $currency
                    && (int) $reservation->amount_minor > 0, 409, 'Invalid refund reservation');
                $amount = Money::ofMinor((int) $reservation->amount_minor, $currency);
                $bookingReserved = $bookingReserved->plus($amount);
                if ((int) $reservation->payment_id === (int) $payment->id) {
                    $reservedAmount = $reservedAmount->plus($amount);
                }
            }
            $priorRefundedMinor = $reservedAmount->getMinorAmount()->toInt();

            $capturedTotal = (int) $payment->amount_minor;
            abort_if(Refund::query()->where('payment_id', $payment->id)
                ->where('status', '!=', RefundStatus::Failed->value)
                ->where('amount_currency', '!=', $currency)->exists(), 409, 'Refund currency mismatch');
            $capturedAmount = Money::ofMinor($capturedTotal, $currency);
            $remaining = Money::min($capturedAmount->minus($reservedAmount), $bookingBudget->minus($bookingReserved));
            $requestedAmount = $dto->requestedAmountMinor ?? $remaining->getMinorAmount()->toInt();
            if ($requestedAmount <= 0) {
                throw ValidationException::withMessages(['amount_minor' => __('payments::refunds.errors.amount_positive')]);
            }
            $reservedTotal = Money::ofMinor($priorRefundedMinor, $currency)
                ->plus(Money::ofMinor($requestedAmount, $currency));

            if ($reservedTotal->isGreaterThan($capturedAmount)) {
                throw new OverRefundAttemptedException(
                    paymentId: $payment->id,
                    capturedTotal: $capturedTotal,
                    alreadyRefunded: $priorRefundedMinor,
                    requested: $requestedAmount,
                );
            }

            if ($bookingReserved->plus(Money::ofMinor($requestedAmount, $currency))->isGreaterThan($bookingBudget)) {
                throw new OverRefundAttemptedException(
                    paymentId: $payment->id,
                    capturedTotal: $bookingBudget->getMinorAmount()->toInt(),
                    alreadyRefunded: $bookingReserved->getMinorAmount()->toInt(),
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
