<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Enums;

enum AdminVendorDecision: string
{
    case Accept = 'accept';
    case Reject = 'reject';
}
