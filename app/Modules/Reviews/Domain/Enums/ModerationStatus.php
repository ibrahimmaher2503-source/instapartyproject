<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Domain\Enums;

use Filament\Support\Contracts\HasLabel;

enum ModerationStatus: string implements HasLabel
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Hidden = 'hidden';

    public function label(): string
    {
        return __('reviews.moderation_status.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function isTerminal(): bool
    {
        return $this === self::Rejected;
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => in_array($target, [self::Approved, self::Rejected], true),
            self::Approved => in_array($target, [self::Hidden, self::Rejected], true),
            self::Hidden => $target === self::Approved,
            self::Rejected => false,
        };
    }
}
