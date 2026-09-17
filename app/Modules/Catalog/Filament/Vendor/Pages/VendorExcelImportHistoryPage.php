<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Pages;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Shared\Application\Services\Concerns\RequiresApprovedVendor;
use Filament\Pages\Page;
use Filament\Tables\Actions\Action as TableAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;

class VendorExcelImportHistoryPage extends Page implements HasTable
{
    use InteractsWithTable;
    use RequiresApprovedVendor;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = 'services';

    protected static ?int $navigationSort = 11;

    protected static string $view = 'vendor-portal.pages.vendor-import-history';

    public static function getNavigationLabel(): string
    {
        return __('catalog.import_history');
    }

    public function getTitle(): string|Htmlable
    {
        return __('catalog.import_history');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('product_type')
                    ->label(__('catalog.product_type'))
                    ->badge()
                    ->color(fn (ProductType $state): string => match ($state) {
                        ProductType::Rental => 'warning',
                        ProductType::Sale => 'success',
                        ProductType::Digital => 'info',
                    })
                    ->formatStateUsing(fn (ProductType $state): string => $state->label()),
                TextColumn::make('original_filename')
                    ->label(__('catalog.original_filename'))
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('status')
                    ->label(__('catalog.status_label'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('total_rows')
                    ->label(__('catalog.total_rows'))
                    ->sortable(),
                TextColumn::make('imported_rows')
                    ->label(__('catalog.imported_rows_count'))
                    ->sortable(),
                TextColumn::make('errors_count')
                    ->label(__('catalog.error_rows'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                TableAction::make('viewErrors')
                    ->label(__('catalog.view_errors'))
                    ->icon('heroicon-o-exclamation-circle')
                    ->color('danger')
                    ->url(fn (ExcelImport $record): string => VendorExcelImportErrorsPage::getUrl([
                        'importPublicId' => $record->public_id,
                    ], panel: 'vendor'))
                    ->visible(fn (ExcelImport $record): bool => $record->status === 'failed'),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        return ExcelImport::query()
            ->where('vendor_profile_id', auth()->user()->vendorProfile->id)
            ->withCount('errors');
    }
}
