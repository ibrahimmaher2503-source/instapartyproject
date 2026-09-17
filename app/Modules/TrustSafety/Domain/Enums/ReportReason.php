<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Domain\Enums;

enum ReportReason: string
{
    case InappropriateContent = 'inappropriate_content';
    case FakeProfile = 'fake_profile';
    case FraudulentActivity = 'fraudulent_activity';
    case Harassment = 'harassment';
    case Other = 'other';

    public function label(): string
    {
        return __('trust-safety::reasons.'.$this->value);
    }
}
