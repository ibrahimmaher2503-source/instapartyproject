<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Enums;

enum FulfillmentIssueStatus: string
{
    case Open = 'open';
    case Acknowledged = 'acknowledged';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
}
