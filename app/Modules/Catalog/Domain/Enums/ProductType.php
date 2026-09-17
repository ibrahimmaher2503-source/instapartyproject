<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProductType: string implements HasLabel
{
    case Rental = 'rental';
    case Sale = 'sale';
    case Digital = 'digital';

    public function label(): string
    {
        return __('catalog.product_types.'.$this->value);
    }

    public function getLabel(): string
    {
        return $this->label();
    }
}
