<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exceptions;

use RuntimeException;

final class DeliveryCityRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Delivery city is required to submit this booking.');
    }
}
