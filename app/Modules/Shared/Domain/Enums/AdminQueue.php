<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\Enums;

enum AdminQueue: string
{
    case VendorApproval = 'vendor_approval';
    case Withdrawal = 'withdrawal';
    case Report = 'report';
    case Support = 'support';

    public function settingKey(): string
    {
        return 'queue_sla_'.$this->value.'_hours';
    }
}
