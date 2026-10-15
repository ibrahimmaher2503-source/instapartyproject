<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exceptions;

use RuntimeException;

final class CurrencyMismatchException extends RuntimeException
{
    public function __construct(string $bookingCurrency, string $coverageCurrency)
    {
        parent::__construct("Coverage area currency ({$coverageCurrency}) does not match booking currency ({$bookingCurrency}).");
    }
}
