<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\ExcelImportResource\Pages;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\ExcelImportError;
use App\Modules\Catalog\Filament\Resources\ExcelImportResource;
use Filament\Actions\Action;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ViewExcelImport extends ViewRecord implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = ExcelImportResource::class;

    public function infolist(Infolist $schema): Infolist
    {
        return $schema
            ->components([
                Section::make(__('catalog.import_details'))
                    ->columns(2)
                    ->schema([
                        TextEntry::make('vendor.business_name')
                            ->label(__('catalog.vendor'))
                            ->formatStateUsing(fn ($state): string => is_array($state)
                                ? ($state[app()->getLocale()] ?? $state['en'] ?? '—')
                                : ($state ?? '—')),
                        TextEntry::make('product_type')
                            ->label(__('catalog.product_type'))
                            ->badge()
                            ->formatStateUsing(fn (ProductType $state): string => $state->label()),
                        TextEntry::make('status')
                            ->label(__('catalog.status_label'))
                            ->badge()
                            ->formatStateUsing(fn (string $state): string => __('catalog.import_status.'.$state, [], $state)),
                        TextEntry::make('original_filename')
                            ->label(__('catalog.original_filename')),
                        TextEntry::make('total_rows')
                            ->label(__('catalog.total_rows')),
                        TextEntry::make('imported_rows')
                            ->label(__('catalog.imported_rows_count')),
                        TextEntry::make('error_rows')
                            ->label(__('catalog.error_rows')),
                        TextEntry::make('created_at')
                            ->label(__('admin.common.created_at'))
                            ->dateTime(),
                    ]),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ExcelImportError::query()
                    ->where('excel_import_id', $this->getRecord()->getKey())
                    ->orderBy('row_number')
            )
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('catalog.row_number'))
                    ->sortable(),
                TextColumn::make('field')
                    ->label(__('catalog.field_name')),
                TextColumn::make('entered_value')
                    ->label(__('catalog.entered_value'))
                    ->getStateUsing(fn (ExcelImportError $record): string => $this->enteredValue($record)),
                TextColumn::make('message_en')
                    ->label(__('catalog.validation_message').' (EN)')
                    ->getStateUsing(fn (ExcelImportError $record): string => $this->message($record, 'en')),
                TextColumn::make('message_ar')
                    ->label(__('catalog.validation_message').' (AR)')
                    ->getStateUsing(fn (ExcelImportError $record): string => $this->message($record, 'ar')),
            ])
            ->defaultSort('row_number')
            ->heading(__('catalog.error_rows'));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadFailedRows')
                ->label(__('catalog.download_failed_rows'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('warning')
                ->visible(fn (): bool => $this->getRecord()->error_rows > 0)
                ->action(function (): StreamedResponse {
                    $import = $this->record;

                    /** @var ExcelImportError[] $errors */
                    $errors = ExcelImportError::query()
                        ->where('excel_import_id', $import->id)
                        ->orderBy('row_number')
                        ->get();

                    $filename = 'failed-rows-'.$import->public_id.'.csv';

                    return response()->streamDownload(function () use ($errors): void {
                        $handle = fopen('php://output', 'w');

                        // Header row
                        fputcsv($handle, ['Row #', 'Field', 'Entered Value', 'Error (EN)', 'Error (AR)']);

                        foreach ($errors as $error) {
                            $enteredValue = $this->enteredValue($error);

                            fputcsv($handle, [
                                $error->row_number,
                                $error->field,
                                $enteredValue,
                                $this->message($error, 'en'),
                                $this->message($error, 'ar'),
                            ]);
                        }

                        fclose($handle);
                    }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
                }),
        ];
    }

    private function enteredValue(ExcelImportError $error): string
    {
        $rowData = $error->row_data;
        $field = $error->field;

        if (! is_array($rowData) || ! is_string($field) || ! array_key_exists($field, $rowData)) {
            return '—';
        }

        $value = $rowData[$field];

        return is_scalar($value) ? Str::limit((string) $value, 120) : '—';
    }

    private function message(ExcelImportError $error, string $locale): string
    {
        $message = $error->message;

        if (! is_array($message) || ! is_scalar($message[$locale] ?? null)) {
            return '';
        }

        return (string) $message[$locale];
    }
}
