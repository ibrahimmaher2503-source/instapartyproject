<?php

declare(strict_types=1);

namespace App\Modules\Support\Filament\Resources\FaqItemResource\Pages;

use App\Modules\Support\Filament\Resources\FaqItemResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditFaqItem extends EditRecord
{
    use Translatable;

    protected static string $resource = FaqItemResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }

    protected function saved(): void
    {
        // Bust the FAQ categories cache and trigger re-indexing
        cache()->forget('faq:categories');
        $this->record->searchable();
    }
}
