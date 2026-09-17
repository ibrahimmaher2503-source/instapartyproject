<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Exceptions;

use RuntimeException;

class NullAdapterInProductionException extends RuntimeException
{
    public function __construct(string $message = 'NullProviderAdapter must never be used in production.')
    {
        parent::__construct($message);
    }
}
