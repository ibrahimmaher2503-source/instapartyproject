<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Domain\Enums;

use Filament\Support\Contracts\HasLabel;

enum PromoCodeScope: string implements HasLabel
{
    case Platform = 'platform';
    case Vendor = 'vendor';
    case Category = 'category';
    case Service = 'service';

    public function label(): string
    {
        return match ($this) {
            self::Platform => __('promotions::promotions.scope.platform'),
            self::Vendor => __('promotions::promotions.scope.vendor'),
            self::Category => __('promotions::promotions.scope.category'),
            self::Service => __('promotions::promotions.scope.service'),
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
