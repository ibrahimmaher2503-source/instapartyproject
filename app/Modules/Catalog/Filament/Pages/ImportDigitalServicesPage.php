<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Pages;

use App\Modules\Catalog\Application\Actions\ImportDigitalServicesFromExcelAction;
use App\Modules\Catalog\Domain\Models\ExcelImport;
use App\Modules\Identity\Application\Actions\SearchVendorProfilesAction;
use App\Modules\Shared\Application\Services\StorefrontText;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ImportDigitalServicesPage extends Page implements HasForms
{
    use InteractsWithForms;

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null
            && $user->can('view_any_vendor::profile')
            && $user->can('create_digital::service');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.services');
    }

    protected static ?string $navigationIcon = 'heroicon-o-arrow-up-tray';

    protected static ?int $navigationSort = 110;

    protected static string $view = 'catalog::filament.pages.import-rental-services';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public ?ExcelImport $lastImport = null;

    public static function getNavigationLabel(): string
    {
        return __('catalog.nav.import_digital_services');
    }

    public function getTitle(): string
    {
        return __('catalog.nav.import_digital_services');
    }

    public function getSubheading(): ?string
    {
        return __('catalog.import_intro');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadTemplate')
                ->label(__('catalog.download_template'))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('admin.catalog.import.template', ['type' => 'digital']))
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
                Select::make('vendor_public_id')
                    ->label(__('catalog.import.vendor'))
                    ->getSearchResultsUsing(fn (string $search): array => app(SearchVendorProfilesAction::class)
                        ->execute(auth()->user(), ['status' => 'approved', 'search' => $search, 'per_page' => 100])
                        ->getCollection()
                        ->mapWithKeys(fn ($vendor): array => [
                            $vendor->public_id => app(StorefrontText::class)->translation($vendor, 'business_name'),
                        ])
                        ->all())
                    ->getOptionLabelUsing(function ($value): ?string {
                        if (! filled($value)) {
                            return null;
                        }

                        $vendor = app(SearchVendorProfilesAction::class)
                            ->execute(auth()->user(), ['status' => 'approved', 'search' => (string) $value, 'per_page' => 100])
                            ->getCollection()
                            ->firstWhere('public_id', (string) $value);

                        return $vendor === null
                            ? null
                            : app(StorefrontText::class)->translation($vendor, 'business_name');
                    })
                    ->searchable()
                    ->required(),
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

    public function import(ImportDigitalServicesFromExcelAction $action): void
    {
        $user = auth()->user();
        abort_unless(
            $user !== null
                && $user->can('view_any_vendor::profile')
                && $user->can('create_digital::service'),
            403,
        );

        $data = $this->form->getState();
        $vendor = app(SearchVendorProfilesAction::class)
            ->execute(auth()->user(), ['status' => 'approved', 'search' => (string) ($data['vendor_public_id'] ?? ''), 'per_page' => 100])
            ->getCollection()
            ->firstWhere('public_id', (string) ($data['vendor_public_id'] ?? ''));
        abort_unless($vendor !== null, 403);

        $absolutePath = Storage::disk('local')->path($data['file']);
        $file = new UploadedFile($absolutePath, basename($absolutePath), null, null, true);

        $this->lastImport = $action->execute($file, (int) $vendor->getKey(), app()->getLocale());

        if ($this->lastImport->status === 'completed') {
            Notification::make()->title(__('catalog.import_success', ['count' => $this->lastImport->imported_rows]))->success()->send();
        } else {
            Notification::make()->title(__('catalog.import_failed'))->danger()->send();
        }

        $this->form->fill();
    }
}
