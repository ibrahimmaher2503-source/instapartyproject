<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource\Pages;

use App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource;
use Filament\Actions\CreateAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListTrustBadges extends ListRecords
{
    use Translatable;

    protected static string $resource = TrustBadgeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
