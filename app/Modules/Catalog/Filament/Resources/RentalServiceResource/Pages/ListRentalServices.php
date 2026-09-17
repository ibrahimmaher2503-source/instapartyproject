<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\RentalServiceResource\Pages;

use App\Modules\Catalog\Filament\Resources\RentalServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRentalServices extends ListRecords
{
    protected static string $resource = RentalServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
