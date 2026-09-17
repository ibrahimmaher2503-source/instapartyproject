<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Enums;

enum FulfillmentLane: string
{
    case Preparing = 'preparing';
    case Ready = 'ready';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case ReportIssue = 'report_issue';
}
