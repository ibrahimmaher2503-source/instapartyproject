<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Domain\Enums;

use Illuminate\Support\Facades\Lang;

enum ReportStatus: string
{
    case Open = 'open';
    case UnderReview = 'under_review';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';

    public function label(): string
    {
        $key = 'trust-safety::trust-safety.statuses.'.$this->value;
        if (Lang::has($key)) {
            return __($key);
        }

        $fallback = 'trust-safety::statuses.'.$this->value;
        if (Lang::has($fallback)) {
            return __($fallback);
        }

        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
