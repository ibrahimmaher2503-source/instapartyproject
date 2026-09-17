<?php

declare(strict_types=1);

namespace App\Modules\Communication\Domain\Enums;

enum CampaignStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('communication.campaign_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Scheduled => 'info',
            self::Running => 'warning',
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Cancelled => 'gray',
        };
    }
}
