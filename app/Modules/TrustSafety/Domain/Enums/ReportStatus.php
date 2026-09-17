<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Domain\Enums;

enum ReportStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        return __('trust-safety::statuses.'.$this->value);
    }
}
