<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Events\PaymentVoided;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\AuthorizedState;
use App\Modules\Payments\Domain\States\PaymentStatus\VoidedState;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidStuckAuthorizationAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
    ) {}

    public function execute(int $paymentId, int $adminUserId, string $reason): Payment
    {
        return DB::transaction(function () use ($paymentId, $adminUserId): Payment {
            /** @var Payment $payment */
            $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);

            if (! $payment->status instanceof AuthorizedState) {
                throw ValidationException::withMessages([
                    'payment' => 'Only authorized payments can be voided.',
                ]);
            }

            $transactionRef = (string) ($payment->metadata['paymob_transaction_id'] ?? $payment->gateway_ref);
            $result = $this->gateway->void($transactionRef);

            if (! $result->success) {
                throw ValidationException::withMessages([
                    'payment' => 'Gateway void failed: '.($result->errorMessage ?? 'unknown error'),
                ]);
            }

            $payment->update(['status' => VoidedState::class]);

            DB::afterCommit(fn () => PaymentVoided::dispatch(
                $payment->id,
                $payment->booking_id,
                $payment->gateway_ref,
                $adminUserId,
            ));

            return $payment->fresh();
        });
    }
}
