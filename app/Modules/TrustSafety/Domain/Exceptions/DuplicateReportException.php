<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Domain\Exceptions;

use RuntimeException;

class DuplicateReportException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('An open report already exists for this target.');
    }
}
