<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Actions;

use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\States\PaymentStatus\FailedState;
use Illuminate\Validation\ValidationException;

class RetryFailedPaymentAction
{
    public function execute(int $paymentId, int $adminUserId): Payment
    {
        $payment = Payment::query()->findOrFail($paymentId);

        if (! $payment->status instanceof FailedState) {
            throw ValidationException::withMessages([
                'payment' => __('payments::payments.ops.retry_failed_only'),
            ]);
        }

        throw ValidationException::withMessages([
            'payment' => __('payments::payments.ops.retry_unsupported'),
        ]);
    }
}
