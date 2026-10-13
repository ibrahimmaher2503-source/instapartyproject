<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource\Pages;

use App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTrustBadge extends EditRecord
{
    protected static string $resource = TrustBadgeResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['name'] = $this->getRecord()->getTranslations('name');
        $data['description'] = $this->getRecord()->getTranslations('description');

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
