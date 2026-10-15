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
