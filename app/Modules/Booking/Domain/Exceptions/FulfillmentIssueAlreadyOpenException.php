<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Exceptions;

use RuntimeException;

class FulfillmentIssueAlreadyOpenException extends RuntimeException
{
    public const CODE = 'fulfillment.issue_already_open';

    public function __construct(string $message = 'An open fulfillment issue already exists for this item.')
    {
        parent::__construct($message);
    }
}
