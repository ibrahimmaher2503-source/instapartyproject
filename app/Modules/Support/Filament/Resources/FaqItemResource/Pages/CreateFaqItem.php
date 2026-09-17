<?php

declare(strict_types=1);

namespace App\Modules\Support\Filament\Resources\FaqItemResource\Pages;

use App\Modules\Support\Filament\Resources\FaqItemResource;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\CreateRecord;
use Filament\Resources\Pages\CreateRecord\Concerns\Translatable;

class CreateFaqItem extends CreateRecord
{
    use Translatable;

    protected static string $resource = FaqItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
        ];
    }
}
