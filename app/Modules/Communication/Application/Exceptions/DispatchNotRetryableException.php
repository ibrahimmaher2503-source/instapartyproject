<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Exceptions;

use App\Modules\Communication\Domain\Models\NotificationDispatch;
use RuntimeException;

class DispatchNotRetryableException extends RuntimeException
{
    public function __construct(
        public readonly NotificationDispatch $dispatch,
        public readonly string $reason,
    ) {
        parent::__construct("Dispatch #{$dispatch->id} is not retryable: {$reason}");
    }
}
