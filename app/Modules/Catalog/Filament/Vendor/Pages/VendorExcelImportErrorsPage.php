<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Pages;

use App\Modules\Catalog\Application\Actions\RetryImportAction;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Catalog\Domain\Models\ExcelImportError;
use App\Modules\Shared\Application\Services\Concerns\RequiresApprovedVendor;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;

class VendorExcelImportErrorsPage extends Page implements HasTable
{
    use InteractsWithTable;
    use RequiresApprovedVendor;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'imports/{importPublicId}/errors';

    protected static string $view = 'vendor-portal.pages.vendor-import-errors';

    public string $importPublicId = '';

    private ExcelImport $import;

    public function mount(string $importPublicId): void
    {
        $import = ExcelImport::query()->where('public_id', $importPublicId)->firstOrFail();

        abort_if(
            $import->vendor_profile_id !== auth()->user()->vendorProfile?->id,
            403
        );

        $this->import = $import;
        $this->importPublicId = $importPublicId;
    }

    public function getTitle(): string|Htmlable
    {
        return __('catalog.import_errors_for', ['filename' => $this->import->original_filename]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->defaultSort('row_number', 'asc')
            ->columns([
                TextColumn::make('row_number')
                    ->label(__('catalog.row_number'))
                    ->sortable(),
                TextColumn::make('field')
                    ->label(__('catalog.field_name')),
                TextColumn::make('entered_value')
                    ->label(__('catalog.entered_value'))
                    ->getStateUsing(function (ExcelImportError $record): string {
                        $value = $record->row_data[$record->field] ?? null;

                        if ($value === null || $value === '') {
                            return '—';
                        }

                        $stringValue = (string) $value;

                        return mb_strlen($stringValue) > 120
                            ? mb_substr($stringValue, 0, 120).'…'
                            : $stringValue;
                    }),
                TextColumn::make('message')
                    ->label(__('catalog.validation_message'))
                    ->getStateUsing(fn (ExcelImportError $record): string => $record->message[app()->getLocale()] ?? $record->message['en'] ?? '')
                    ->wrap(),
                TextColumn::make('fix_hint')
                    ->label(__('catalog.required_fix'))
                    ->getStateUsing(fn (ExcelImportError $record): string => $this->resolveFixHint($record))
                    ->wrap(),
            ])
            ->headerActions([
                Action::make('retryImport')
                    ->label(__('catalog.retry_import'))
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (): bool => $this->import->status === 'failed')
                    ->form([
                        FileUpload::make('file')
                            ->label(__('catalog.import_file_label'))
                            ->required()
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'text/csv',
                                'application/vnd.ms-excel',
                            ]),
                    ])
                    ->action(function (array $data): void {
                        /** @var UploadedFile $file */
                        $file = $data['file'];

                        app(RetryImportAction::class)->execute(
                            $this->import,
                            $file,
                            auth()->user()->vendorProfile->id
                        );

                        Notification::make()
                            ->title(__('catalog.retry_import_queued'))
                            ->success()
                            ->send();

                        $this->redirect(VendorExcelImportHistoryPage::getUrl());
                    }),
                Action::make('downloadFailedRows')
                    ->label(__('catalog.download_failed_rows'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->url(fn (): string => route('vendor.catalog.import.failed-rows', ['importPublicId' => $this->import->public_id]))
                    ->openUrlInNewTab()
                    ->visible(fn (): bool => $this->import->error_rows > 0),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        return ExcelImportError::query()
            ->where('excel_import_id', $this->import->id)
            ->orderBy('row_number');
    }

    private function resolveFixHint(ExcelImportError $record): string
    {
        $enMessage = strtolower($record->message['en'] ?? '');

        if (str_contains($enMessage, 'required')) {
            return __('catalog.fix_hint_required');
        }

        if (str_contains($enMessage, 'integer') || str_contains($enMessage, 'whole number')) {
            return __('catalog.fix_hint_integer');
        }

        if (str_contains($enMessage, 'must be 0') || str_contains($enMessage, 'min:0')) {
            return __('catalog.fix_hint_min_0');
        }

        if (str_contains($enMessage, 'must be 1') || str_contains($enMessage, 'min:1')) {
            return __('catalog.fix_hint_min_1');
        }

        if (str_contains($enMessage, '255') || str_contains($enMessage, 'max:255')) {
            return __('catalog.fix_hint_max_255');
        }

        if (str_contains($enMessage, 'boolean') || str_contains($enMessage, 'true') || str_contains($enMessage, 'false')) {
            return __('catalog.fix_hint_boolean');
        }

        if (str_contains($enMessage, 'string') || str_contains($enMessage, 'must be text')) {
            return __('catalog.fix_hint_string');
        }

        return __('catalog.fix_hint_default');
    }
}
