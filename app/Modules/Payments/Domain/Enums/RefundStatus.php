<?php

declare(strict_types=1);

namespace App\Modules\Payments\Domain\Enums;

enum RefundStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return __('refunds.status.'.$this->value);
    }
}
