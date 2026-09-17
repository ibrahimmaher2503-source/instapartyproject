<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exceptions;

use RuntimeException;

class RefundInProgressException extends RuntimeException
{
    public const CODE = 'fulfillment.refund_in_progress';

    public function __construct(string $message = 'A refund is in progress for this booking; fulfillment is blocked.')
    {
        parent::__construct($message);
    }
}
