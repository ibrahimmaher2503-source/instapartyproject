<?php

declare(strict_types=1);

namespace App\Modules\Communication\Filament\Resources\NotificationTemplateResource\Pages;

use App\Modules\Communication\Filament\Resources\NotificationTemplateResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\LocaleSwitcher;
use Filament\Resources\Pages\EditRecord;
use Filament\Resources\Pages\EditRecord\Concerns\Translatable;

class EditNotificationTemplate extends EditRecord
{
    use Translatable;

    protected static string $resource = NotificationTemplateResource::class;

    protected function getHeaderActions(): array
    {
        return [
            LocaleSwitcher::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $validationData = [
            'body' => $this->record->getTranslations('body'),
            'subject' => $this->record->getTranslations('subject'),
            'variables' => $data['variables'] ?? $this->record->variables ?? [],
        ];

        foreach ($this->otherLocaleData as $locale => $localeData) {
            foreach (['body', 'subject'] as $field) {
                if (isset($localeData[$field])) {
                    $validationData[$field][$locale] = $localeData[$field];
                }
            }
        }

        foreach (['body', 'subject'] as $field) {
            if (isset($data[$field])) {
                $validationData[$field][$this->activeLocale] = $data[$field];
            }
        }

        NotificationTemplateResource::validateFormData($validationData);

        return $data;
    }
}
