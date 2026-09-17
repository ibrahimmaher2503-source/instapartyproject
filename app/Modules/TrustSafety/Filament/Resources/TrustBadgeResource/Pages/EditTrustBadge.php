<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource\Pages;

use App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditTrustBadge extends EditRecord
{
    use Translatable;

    protected static string $resource = TrustBadgeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }
}
