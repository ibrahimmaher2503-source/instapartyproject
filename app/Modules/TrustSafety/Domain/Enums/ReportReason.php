<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Domain\Enums;

use Illuminate\Support\Facades\Lang;

enum ReportReason: string
{
    case InappropriateContent = 'inappropriate_content';
    case FakeProfile = 'fake_profile';
    case FraudulentActivity = 'fraudulent_activity';
    case Harassment = 'harassment';
    case Other = 'other';

    public function label(): string
    {
        $key = 'trust-safety::trust-safety.reasons.'.$this->value;
        if (Lang::has($key)) {
            return __($key);
        }

        $fallback = 'trust-safety::reasons.'.$this->value;
        if (Lang::has($fallback)) {
            return __($fallback);
        }

        return str($this->value)->replace('_', ' ')->title()->toString();
    }
}
