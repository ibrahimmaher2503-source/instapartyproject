<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Application\Listeners;

use App\Modules\Payments\Domain\Events\PaymentCaptured;

class OnPaymentCaptured
{
    public function handle(PaymentCaptured $event): void
    {
        // Stub: subscription usage metering is Phase 1.7 — not yet implemented.
    }
}
