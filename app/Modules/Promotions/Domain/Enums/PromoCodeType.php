<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Domain\Enums;

use Filament\Support\Contracts\HasLabel;

enum PromoCodeType: string implements HasLabel
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => __('promotions::promotions.type.percentage'),
            self::Fixed => __('promotions::promotions.type.fixed'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
