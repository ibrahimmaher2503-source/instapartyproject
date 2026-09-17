<?php

declare(strict_types=1);

namespace App\Modules\Support\Filament\Resources\FaqItemResource\Pages;

use App\Modules\Support\Filament\Resources\FaqItemResource;
use Filament\Actions\CreateAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListFaqItems extends ListRecords
{
    use Translatable;

    protected static string $resource = FaqItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
