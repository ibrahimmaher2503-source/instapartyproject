<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Filament\Resources\AdvertisementPackageResource\Pages;

use App\Modules\Advertising\Filament\Resources\AdvertisementPackageResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditAdvertisementPackage extends EditRecord
{
    use Translatable;

    protected static string $resource = AdvertisementPackageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }
}
