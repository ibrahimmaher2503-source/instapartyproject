<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorProfileResource\Pages;

use App\Modules\Identity\Application\Actions\AdminReplaceVendorCoverageAction;
use App\Modules\Identity\Application\Actions\UpdateVendorProfileAction;
use App\Modules\Identity\Application\Actions\UpsertVendorBusinessHoursAction;
use App\Modules\Identity\Application\Support\MaskBankData;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Resources\VendorProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditVendorProfile extends EditRecord
{
    protected static string $resource = VendorProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (MaskBankData::FIELDS as $field) {
            $data[$field] = MaskBankData::forField($field, $this->getRecord()->getRawOriginal($field));
        }

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        foreach (MaskBankData::FIELDS as $field) {
            if (array_key_exists($field, $data)
                && $data[$field] === MaskBankData::forField($field, $this->getRecord()->getRawOriginal($field))) {
                unset($data[$field]);
            }
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var VendorProfile $record */
        app(UpdateVendorProfileAction::class)->execute($record, $data);

        $hours = $data['business_hours'] ?? [];
        if ($hours !== []) {
            app(UpsertVendorBusinessHoursAction::class)->execute($record, $hours);
        }

        $areas = $data['coverage_areas'] ?? [];
        if ($areas !== []) {
            app(AdminReplaceVendorCoverageAction::class)->execute($record, $areas);
        }

        return $record->refresh();
    }
}
