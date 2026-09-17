<?php

declare(strict_types=1);

namespace App\Modules\Support\Filament\Resources\FaqCategoryResource\Pages;

use App\Modules\Support\Filament\Resources\FaqCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\ListRecords;
use Filament\Resources\Pages\ListRecords\Concerns\Translatable;

class ListFaqCategories extends ListRecords
{
    use Translatable;

    protected static string $resource = FaqCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            CreateAction::make(),
        ];
    }
}
