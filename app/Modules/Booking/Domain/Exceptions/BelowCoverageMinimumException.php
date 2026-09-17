<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exceptions;

use App\Modules\Booking\Application\DTOs\CoverageMinimumFailureDTO;
use RuntimeException;

final class BelowCoverageMinimumException extends RuntimeException
{
    /** @param CoverageMinimumFailureDTO[] $failures */
    public function __construct(private readonly array $failures)
    {
        parent::__construct('One or more vendors are below the coverage area minimum order amount.');
    }

    /** @return CoverageMinimumFailureDTO[] */
    public function failures(): array
    {
        return $this->failures;
    }
}

final class DeliveryCityRequiredException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Delivery city is required to submit this booking.');
    }
}

final class CurrencyMismatchException extends RuntimeException
{
    public function __construct(string $bookingCurrency, string $coverageCurrency)
    {
        parent::__construct(
            "Coverage area currency ({$coverageCurrency}) does not match booking currency ({$bookingCurrency})."
        );
    }
}
