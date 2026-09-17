<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Filament\Resources\PromoCodeResource\Pages;

use App\Modules\Promotions\Filament\Resources\PromoCodeResource;
use Filament\Resources\Pages\CreateRecord;

class CreatePromoCode extends CreateRecord
{
    protected static string $resource = PromoCodeResource::class;
}
