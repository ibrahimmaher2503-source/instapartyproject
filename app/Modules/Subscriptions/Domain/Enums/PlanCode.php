<?php

declare(strict_types=1);

namespace App\Modules\Subscriptions\Domain\Enums;

use Filament\Support\Contracts\HasLabel;

enum PlanCode: string implements HasLabel
{
    case Free = 'free';
    case Silver = 'silver';
    case Gold = 'gold';
    case Premium = 'premium';

    public function label(): string
    {
        return __('subscriptions::subscription.plan.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function isFreeTier(): bool
    {
        return $this === self::Free;
    }

    /**
     * Returns tiers ranked lower than this one (useful for determining "unblocking" tier).
     *
     * @return list<self>
     */
    public function upgradeOptions(): array
    {
        return match ($this) {
            self::Free => [self::Silver, self::Gold, self::Premium],
            self::Silver => [self::Gold, self::Premium],
            self::Gold => [self::Premium],
            self::Premium => [],
        };
    }

    public function firstUpgrade(): ?self
    {
        return $this->upgradeOptions()[0] ?? null;
    }
}
