<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\CategoryFieldSchemaResource\Pages;

use App\Modules\Catalog\Application\Actions\DeleteCategoryFieldSchemaAction;
use App\Modules\Catalog\Application\Actions\UpdateCategoryFieldSchemaAction;
use App\Modules\Catalog\Application\DTOs\CategoryFieldSchemaDTO;
use App\Modules\Catalog\Domain\Models\CategoryFieldSchema;
use App\Modules\Catalog\Filament\Resources\CategoryFieldSchemaResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditCategoryFieldSchema extends EditRecord
{
    protected static string $resource = CategoryFieldSchemaResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $options = $data['options'] ?? null;

        if ($options === null || $options === []) {
            return $data;
        }

        if (! is_array($options)) {
            $this->rejectUnsupportedOptions();
        }

        if (array_is_list($options)) {
            if ($this->hasCanonicalOptions($options)) {
                return $data;
            }

            $rows = [];

            foreach ($options as $option) {
                if (! is_scalar($option) && $option !== null) {
                    $this->rejectUnsupportedOptions();
                }

                $valueAndLabel = (string) $option;
                $rows[] = [
                    'value' => $valueAndLabel,
                    'label' => [
                        'en' => $valueAndLabel,
                        'ar' => $valueAndLabel,
                    ],
                ];
            }

            $data['options'] = $rows;

            return $data;
        }

        $rows = [];

        foreach ($options as $value => $label) {
            if (! is_string($value) && ! is_int($value)) {
                $this->rejectUnsupportedOptions();
            }

            if (is_array($label)) {
                if (array_diff(array_keys($label), ['en', 'ar']) !== []) {
                    $this->rejectUnsupportedOptions();
                }

                foreach ($label as $localizedLabel) {
                    if (! is_scalar($localizedLabel) && $localizedLabel !== null) {
                        $this->rejectUnsupportedOptions();
                    }
                }

                $englishLabel = (string) ($label['en'] ?? $value);
                $arabicLabel = (string) ($label['ar'] ?? $label['en'] ?? $value);
            } elseif (is_scalar($label) || $label === null) {
                $englishLabel = $arabicLabel = (string) $label;
            } else {
                $this->rejectUnsupportedOptions();
            }

            $rows[] = [
                'value' => (string) $value,
                'label' => [
                    'en' => $englishLabel,
                    'ar' => $arabicLabel,
                ],
            ];
        }

        $data['options'] = $rows;

        return $data;
    }

    /** @param list<mixed> $options */
    private function hasCanonicalOptions(array $options): bool
    {
        foreach ($options as $option) {
            if (
                ! is_array($option)
                || array_diff(array_keys($option), ['value', 'label']) !== []
                || ! is_string($option['value'] ?? null)
                || ! is_array($option['label'] ?? null)
                || array_diff(array_keys($option['label']), ['en', 'ar']) !== []
                || ! is_string($option['label']['en'] ?? null)
                || ! is_string($option['label']['ar'] ?? null)
            ) {
                return false;
            }
        }

        return true;
    }

    private function rejectUnsupportedOptions(): never
    {
        throw ValidationException::withMessages([
            'data.options' => __('catalog.options_unsupported_format'),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->using(function (CategoryFieldSchema $record): bool {
                    app(DeleteCategoryFieldSchemaAction::class)->execute($record, auth()->user());

                    return true;
                }),
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(UpdateCategoryFieldSchemaAction::class)->execute(
            $record,
            CategoryFieldSchemaDTO::fromArray($data),
            auth()->user(),
        );
    }
}
