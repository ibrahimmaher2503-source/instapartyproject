<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\AppSettingResource\Pages;

use App\Modules\Shared\Filament\Resources\AppSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditAppSetting extends EditRecord
{
    protected static string $resource = AppSettingResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $decoded = json_decode($data['value'] ?? 'null', true);
        $isScalar = ! is_array($decoded) && $decoded !== null;
        $data['_is_scalar'] = $isScalar;
        $data['value_text'] = $isScalar ? (string) $decoded : null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['_is_scalar'] ?? false) {
            $data['value'] = json_encode($data['value_text'] ?? '');
        }
        unset($data['_is_scalar'], $data['value_text']);

        return $data;
    }
}
