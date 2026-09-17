<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Filament\Resources;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Discovery\Domain\Models\SavedSearch;
use App\Modules\Discovery\Filament\Resources\SavedSearchResource\Pages\ListSavedSearches;
use Brick\Money\Money;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SavedSearchResource extends Resource
{
    protected static ?string $model = SavedSearch::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.discovery');
    }

    protected static ?string $navigationIcon = 'heroicon-o-bookmark';

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('discovery.nav.saved_searches');
    }

    public static function getModelLabel(): string
    {
        return __('discovery.models.saved_search.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('discovery.models.saved_search.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['user']);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label(__('discovery.columns.user_id'))
                    ->searchable(),
                TextColumn::make('label')
                    ->label(__('discovery.columns.label'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('filters')
                    ->label(__('discovery.columns.filters'))
                    ->getStateUsing(fn (SavedSearch $record): string => self::formatFilters($record->filters ?? []))
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSavedSearches::route('/'),
        ];
    }

    /** @param array<string, mixed> $filters */
    private static function formatFilters(array $filters): string
    {
        if ($filters === []) {
            return __('discovery.search.no_filters');
        }

        $parts = [];

        foreach ($filters as $key => $value) {
            $label = match ($key) {
                'product_type', 'product_types' => __('discovery.filters.product_types'),
                'city' => __('discovery.filters.city'),
                'max_price_minor' => __('discovery.filters.max_price'),
                'locale' => __('discovery.filters.locale'),
                default => Str::headline($key),
            };

            $formatted = match ($key) {
                'product_type', 'product_types' => collect((array) $value)
                    ->map(fn (mixed $type): string => ProductType::tryFrom((string) $type)?->label() ?? (string) $type)
                    ->implode(', '),
                'max_price_minor' => Money::ofMinor((int) $value, 'EGP')->formatTo(app()->getLocale()),
                default => is_array($value) ? implode(', ', array_map('strval', $value)) : (string) $value,
            };

            $parts[] = $label.': '.$formatted;
        }

        return implode(' · ', $parts);
    }
}
