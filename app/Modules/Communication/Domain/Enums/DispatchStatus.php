<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Enums;

enum DispatchStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case Bounced = 'bounced';
    case Suppressed = 'suppressed';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
            self::Bounced => 'Bounced',
            self::Suppressed => __('communication.dispatch_status.suppressed'),
        };
    }
}
