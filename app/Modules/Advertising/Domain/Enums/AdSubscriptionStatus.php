<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Domain\Enums;

enum AdSubscriptionStatus: string
{
    case PendingPayment = 'pending_payment';
    case Active = 'active';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return __('advertising.subscription_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::PendingPayment => 'warning',
            self::Active => 'success',
            self::Expired => 'gray',
            self::Cancelled => 'danger',
        };
    }
}
