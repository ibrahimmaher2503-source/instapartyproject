<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\AppSettingResource\Pages;

use App\Modules\Shared\Application\Actions\UpdateAppSettingAction;
use App\Modules\Shared\Domain\Enums\AdminQueue;
use App\Modules\Shared\Filament\Resources\AppSettingResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class EditAppSetting extends EditRecord
{
    protected static string $resource = AppSettingResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $decoded = $data['value'] ?? null;
        $isScalar = ! is_array($decoded) && $decoded !== null;
        $data['_is_scalar'] = $isScalar;
        $data['value_text'] = $isScalar
            ? (is_bool($decoded) ? ($decoded ? 'true' : 'false') : (string) $decoded)
            : null;

        return $data;
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($data['_is_scalar'] ?? false) {
            $valueText = (string) ($data['value_text'] ?? '');
            $originalValue = $this->record->getAttribute('value');
            $validatedText = is_bool($originalValue) ? strtolower(trim($valueText)) : $valueText;
            $rules = match (true) {
                is_bool($originalValue) => ['required', 'in:true,false,1,0,yes,no,on,off'],
                is_int($originalValue) => ['required', 'integer'],
                is_float($originalValue) => ['required', 'numeric'],
                default => ['string'],
            };
            if (in_array($this->record->key, array_map(fn (AdminQueue $queue): string => $queue->settingKey(), AdminQueue::cases()), true)) {
                $rules = ['required', 'integer', 'between:1,720'];
            }

            Validator::make(
                ['data' => ['value_text' => $validatedText]],
                ['data.value_text' => $rules],
                attributes: ['data.value_text' => __('shared.settings.value')],
            )->validate();
            $data['value'] = match (true) {
                is_bool($originalValue) => filter_var($validatedText, FILTER_VALIDATE_BOOLEAN),
                is_int($originalValue) => (int) $valueText,
                is_float($originalValue) => (float) $valueText,
                default => $valueText,
            };
        }
        unset($data['_is_scalar'], $data['value_text']);

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateAppSettingAction::class)->execute($record, $data);
    }
}
