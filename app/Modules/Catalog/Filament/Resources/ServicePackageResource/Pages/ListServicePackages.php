<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\ServicePackageResource\Pages;

use App\Modules\Catalog\Filament\Resources\ServicePackageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListServicePackages extends ListRecords
{
    protected static string $resource = ServicePackageResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
