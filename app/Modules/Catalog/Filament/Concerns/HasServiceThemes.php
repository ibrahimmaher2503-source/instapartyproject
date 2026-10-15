<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Concerns;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceTheme;
use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Builder;

trait HasServiceThemes
{
    protected static function serviceThemesField(): Select
    {
        return Select::make('themes')
            ->label(__('catalog.service_themes'))
            ->helperText(__('catalog.service_themes_association_helper'))
            ->multiple()
            ->relationship(
                'themes',
                'code',
                modifyQueryUsing: fn (Builder $query, ?Service $record): Builder => $query->where(function (Builder $query) use ($record): void {
                    $query->where('is_active', true);

                    if ($record !== null) {
                        $query->orWhereHas('services', fn (Builder $services) => $services->whereKey($record->getKey()));
                    }
                }),
            )
            ->getOptionLabelFromRecordUsing(fn (ServiceTheme $record): string => $record->getTranslation('name', app()->getLocale(), useFallbackLocale: true)
                .' ('.$record->code.')'
                .($record->is_active ? '' : ' — '.__('catalog.inactive')))
            ->searchable()
            ->preload()
            ->optionsLimit(50)
            ->rules(fn (?Service $record): array => [
                function (string $attribute, mixed $value, \Closure $fail) use ($record): void {
                    if (! is_array($value)) {
                        $fail(__('catalog.service_themes_invalid'));

                        return;
                    }

                    $allowedIds = ServiceTheme::query()
                        ->where('is_active', true)
                        ->when($record !== null, fn (Builder $query) => $query->orWhereHas(
                            'services',
                            fn (Builder $services) => $services->whereKey($record->getKey()),
                        ))
                        ->pluck('id')
                        ->map(static fn (int $id): string => (string) $id)
                        ->all();

                    foreach ($value as $id) {
                        if ((! is_int($id) && (! is_string($id) || ! ctype_digit($id)))
                            || ! in_array((string) $id, $allowedIds, true)) {
                            $fail(__('catalog.service_themes_invalid'));

                            return;
                        }
                    }
                },
            ])
            ->nestedRecursiveRules(['integer', 'distinct']);
    }
}
