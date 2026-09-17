<?php

declare(strict_types=1);

namespace App\Modules\Support\Filament\Resources\FaqCategoryResource\Pages;

use App\Modules\Support\Filament\Resources\FaqCategoryResource;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateFaqCategory extends CreateRecord
{
    use Translatable;

    protected static string $resource = FaqCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }
}
