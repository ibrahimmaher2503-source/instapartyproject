<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\DigitalServiceResource\Pages;

use App\Modules\Catalog\Filament\Resources\DigitalServiceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListDigitalServices extends ListRecords
{
    protected static string $resource = DigitalServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
