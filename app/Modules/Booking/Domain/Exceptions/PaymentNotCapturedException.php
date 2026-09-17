<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exceptions;

use RuntimeException;

class PaymentNotCapturedException extends RuntimeException
{
    public const CODE = 'fulfillment.payment_required';

    public function __construct(string $message = 'Payment must be captured before fulfillment can proceed.')
    {
        parent::__construct($message);
    }
}
