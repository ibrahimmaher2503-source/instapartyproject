<?php

declare(strict_types=1);

namespace App\Modules\Booking\Domain\Enums;

use Filament\Support\Contracts\HasLabel;

enum ModificationStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Pending = 'pending';
    case CustomerAccepted = 'customer_accepted';
    case CustomerRejected = 'customer_rejected';
    case Withdrawn = 'withdrawn';
    case Expired = 'expired';

    public function getLabel(): string
    {
        return __("booking.modification_status.{$this->value}");
    }
}
