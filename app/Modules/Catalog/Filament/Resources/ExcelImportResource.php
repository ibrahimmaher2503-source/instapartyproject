<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Catalog\Filament\Resources\ExcelImportResource\Pages\ListExcelImports;
use App\Modules\Catalog\Filament\Resources\ExcelImportResource\Pages\ViewExcelImport;
use App\Modules\Shared\Application\Services\StorefrontText;
use Filament\Resources\Resource;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ExcelImportResource extends Resource
{
    protected static ?string $model = ExcelImport::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.services');
    }

    protected static ?string $navigationIcon = 'heroicon-o-document-arrow-up';

    protected static ?int $navigationSort = 80;

    public static function getNavigationLabel(): string
    {
        return __('catalog.nav.excel_imports');
    }

    public static function getModelLabel(): string
    {
        return __('catalog.models.excel_import.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.models.excel_import.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('errors')->with(['vendor']);
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

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('original_filename')
                    ->label(__('catalog.original_filename'))
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('vendor.business_name')
                    ->label(__('catalog.vendor'))
                    ->getStateUsing(fn (ExcelImport $record): string => $record->vendor === null
                        ? '—'
                        : (app(StorefrontText::class)->translation($record->vendor, 'business_name') ?: '—'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'vendor',
                        fn ($q) => $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(business_name, '$.en')) LIKE ?", ["%{$search}%"])
                    )),
                TextColumn::make('product_type')
                    ->label(__('catalog.product_type'))
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (ProductType $state): string => $state->label()),
                TextColumn::make('status')
                    ->label(__('catalog.status_label'))
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'completed' => 'success',
                        'processing' => 'info',
                        'failed' => 'danger',
                        default => 'warning',
                    })
                    ->formatStateUsing(fn (string $state): string => __('catalog.import_status.'.$state))
                    ->sortable(),
                TextColumn::make('imported_rows')
                    ->label(__('catalog.imported_rows_count'))
                    ->sortable(),
                TextColumn::make('errors_count')
                    ->label(__('catalog.error_rows'))
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'gray')
                    ->sortable(),
                TextColumn::make('total_rows')
                    ->label(__('catalog.total_rows'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('public_id')
                    ->label(__('catalog.public_id'))
                    ->copyable()
                    ->searchable()
                    ->limit(14)
                    ->tooltip(fn (ExcelImport $record): string => $record->public_id)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make(),
            ])
            ->filters([
                SelectFilter::make('product_type')
                    ->label(__('catalog.product_type'))
                    ->options(collect(ProductType::cases())->mapWithKeys(fn (ProductType $type): array => [$type->value => $type->label()])),
                SelectFilter::make('status')
                    ->label(__('catalog.status_label'))
                    ->options([
                        'pending' => __('catalog.import_status.pending'),
                        'processing' => __('catalog.import_status.processing'),
                        'completed' => __('catalog.import_status.completed'),
                        'failed' => __('catalog.import_status.failed'),
                    ]),
            ])
            ->emptyStateIcon('heroicon-o-document-arrow-up')
            ->emptyStateHeading(__('catalog.import_empty_heading'))
            ->emptyStateDescription(__('catalog.import_empty_description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExcelImports::route('/'),
            'view' => ViewExcelImport::route('/{record}'),
        ];
    }
}
