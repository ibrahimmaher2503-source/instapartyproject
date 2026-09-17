<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Enums;

use Filament\Support\Contracts\HasLabel;

enum SubscriptionStatus: string implements HasLabel
{
    case Active = 'active';
    case PastDue = 'past_due';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
    case Superseded = 'superseded';

    public function label(): string
    {
        return __('subscriptions::subscription.status.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function isTerminal(): bool
    {
        return match ($this) {
            self::Cancelled, self::Expired, self::Superseded => true,
            default => false,
        };
    }

    public function isActiveForGating(): bool
    {
        return match ($this) {
            self::Active, self::PastDue => true,
            default => false,
        };
    }
}
