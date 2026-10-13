<?php

declare(strict_types=1);

namespace App\Modules\Tax\Filament\Resources\TaxRateResource\Pages;

use App\Modules\Tax\Application\Actions\UpdateTaxRateAction;
use App\Modules\Tax\Filament\Resources\TaxRateResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTaxRate extends EditRecord
{
    protected static string $resource = TaxRateResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            ...$data,
            'name' => $this->record->getTranslations('name'),
            'description' => $this->record->getTranslations('description'),
        ];
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateTaxRateAction::class)->execute($record, $data);
    }
}
