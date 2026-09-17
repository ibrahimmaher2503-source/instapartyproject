<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Actions;

use App\Modules\Booking\Domain\Models\Booking;

final class ResolveAvailablePaymentMethodsAction
{
    /** @return string[] */
    public function execute(Booking $booking): array
    {
        // Phase 1: return all configured gateway methods
        return config('payment.customer_payment_methods', ['paymob_card', 'paymob_wallet']);
    }
}
