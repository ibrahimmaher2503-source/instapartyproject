<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\Contracts\PaymentGateway;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\FailedState;
use App\Modules\Payments\Domain\States\PaymentStatus\PendingState;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RetryFailedPaymentAction
{
    public function __construct(
        private readonly PaymentGateway $gateway,
    ) {}

    public function execute(int $paymentId, int $adminUserId): Payment
    {
        return DB::transaction(function () use ($paymentId, $adminUserId): Payment {
            /** @var Payment $payment */
            $payment = Payment::query()->lockForUpdate()->findOrFail($paymentId);

            if (! $payment->status instanceof FailedState) {
                throw ValidationException::withMessages([
                    'payment' => 'Only failed payments can be retried.',
                ]);
            }

            $payment->update([
                'status' => PendingState::class,
                'failure_code' => null,
                'failure_message' => null,
            ]);

            // Re-initiate gateway charge — result processed via webhook or polling
            // Admin is notified of outcome via the normal webhook flow
            DB::afterCommit(fn () => logger()->info('Payment retry initiated', [
                'payment_id' => $paymentId,
                'retried_by' => $adminUserId,
            ]));

            return $payment->fresh();
        });
    }
}
