<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\NavigationMenuResource\Pages;

use App\Modules\Shared\Domain\Models\NavigationMenu;
use App\Modules\Shared\Domain\Models\NavigationMenuItem;
use App\Modules\Shared\Filament\Resources\NavigationMenuResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditNavigationMenu extends EditRecord
{
    protected static string $resource = NavigationMenuResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        $toForm = static fn (NavigationMenuItem $item): array => array_replace($item->getAttributes(), [
            'label' => $item->getTranslations('label'),
        ]);
        $data['items'] = $this->record->rootItems()->with('children')->get()
            ->map(static fn (NavigationMenuItem $item): array => array_replace($toForm($item), [
                'children' => $item->children->map($toForm)->all(),
            ]))->all();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var NavigationMenu $record */
        $data['slot'] = $record->slot->value;

        return NavigationMenuResource::persistViaAction($data);
    }
}
