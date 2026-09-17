<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Enums;

enum FulfillmentIssueReason: string
{
    case VenueUnavailable = 'venue_unavailable';
    case CustomerUnreachable = 'customer_unreachable';
    case DamagedGoods = 'damaged_goods';
    case SafetyConcern = 'safety_concern';
    case Other = 'other';

    public function label(): string
    {
        return (string) __('vendor-portal.fulfillment.issue_reason.'.$this->value);
    }
}
