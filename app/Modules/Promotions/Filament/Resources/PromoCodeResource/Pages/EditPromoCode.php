<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Filament\Resources\PromoCodeResource\Pages;

use App\Modules\Promotions\Filament\Resources\PromoCodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditPromoCode extends EditRecord
{
    protected static string $resource = PromoCodeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
