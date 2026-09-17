<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Pages;

use App\Modules\Catalog\Application\Actions\ImportRentalServicesFromExcelAction;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImportRentalServicesPage extends Page implements HasForms
{
    use InteractsWithForms;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.services');
    }

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('catalog.nav.import_rental_services');
    }

    public function getTitle(): string
    {
        return __('catalog.nav.import_rental_services');
    }

    public function getSubheading(): ?string
    {
        return __('catalog.import_intro');
    }

    protected static string $view = 'catalog::filament.pages.import-rental-services';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?ExcelImport $lastImport = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label(__('catalog.download_template'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('admin.catalog.import.template', ['type' => 'rental']))
                ->openUrlInNewTab(),
        ];
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $schema): Form
    {
        return $schema
            ->components([
                FileUpload::make('file')
                    ->label(__('catalog.import_file_label'))
                    ->helperText(__('catalog.import_supported_formats'))
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                    ])
                    ->required()
                    ->previewable(false)
                    ->disk('local')
                    ->directory('excel-imports-temp'),
            ])
            ->statePath('data');
    }

    public function import(ImportRentalServicesFromExcelAction $action): void
    {
        $data = $this->form->getState();

        $vendor = auth()->user()?->vendorProfile;

        if ($vendor === null) {
            Notification::make()
                ->title(__('catalog.import_no_vendor'))
                ->danger()
                ->send();

            return;
        }

        $absolutePath = Storage::disk('local')->path($data['file']);
        $file = new UploadedFile($absolutePath, basename($absolutePath), null, null, true);

        $this->lastImport = $action->execute($file, $vendor->id, app()->getLocale());

        if ($this->lastImport->status === 'completed') {
            Notification::make()
                ->title(__('catalog.import_success', ['count' => $this->lastImport->imported_rows]))
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title(__('catalog.import_failed'))
                ->danger()
                ->send();
        }

        $this->form->fill();
    }
}
