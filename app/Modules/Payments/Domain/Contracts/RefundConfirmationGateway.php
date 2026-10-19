<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Contracts;

use App\Modules\Payments\Domain\Models\Payment;
use Brick\Money\Money;

interface RefundConfirmationGateway
{
    /** Confirm an existing refund from provider state without issuing a refund request. */
    public function confirmRefund(
        Payment $payment,
        string $callbackTransactionId,
        Money $refundAmount,
        Money $expectedCumulativeAmount,
    ): bool;
}
