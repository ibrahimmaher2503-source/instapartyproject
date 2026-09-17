<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources;

use App\Modules\Catalog\Application\Actions\ImportSaleServicesFromExcelAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\ServiceState;
use App\Modules\Catalog\Filament\Actions\ApproveServiceAction;
use App\Modules\Catalog\Filament\Actions\BulkApproveServicesAction;
use App\Modules\Catalog\Filament\Actions\BulkArchiveServicesAction;
use App\Modules\Catalog\Filament\Actions\BulkRejectServicesAction;
use App\Modules\Catalog\Filament\Actions\RejectServiceAction;
use App\Modules\Catalog\Filament\Actions\RequestServiceEditsAction;
use App\Modules\Catalog\Filament\Resources\SaleServiceResource\Pages\CreateSaleService;
use App\Modules\Catalog\Filament\Resources\SaleServiceResource\Pages\EditSaleService;
use App\Modules\Catalog\Filament\Resources\SaleServiceResource\Pages\ListSaleServices;
use App\Modules\Catalog\Filament\Resources\SaleServiceResource\Pages\PendingSaleServicesPage;
use App\Modules\Catalog\Filament\Resources\SaleServiceResource\Pages\ViewSaleService;
use App\Modules\Discovery\Filament\Actions\ReindexServicesAction;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Application\Services\StorefrontText;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Filament\Infolists\Components\AuditTimelineSection;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SaleServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.services');
    }

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('catalog.nav.sale_services');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Service::query()
            ->forType(ProductType::Sale)
            ->pendingReview()
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getModelLabel(): string
    {
        return __('catalog.models.sale_service.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.models.sale_service.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->where('product_type', ProductType::Sale);
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Tabs::make(__('catalog.translations'))
                ->tabs([
                    Tab::make(__('catalog.language_english'))
                        ->schema([
                            TextInput::make('name.en')
                                ->markAsRequired()
                                ->rule('required')
                                ->maxLength(255)
                                ->label(__('catalog.name')),
                            Textarea::make('short_description.en')
                                ->markAsRequired()
                                ->rule('required')
                                ->maxLength(1000)
                                ->rows(3)
                                ->label(__('catalog.short_description')),
                            Textarea::make('long_description.en')
                                ->maxLength(5000)
                                ->rows(5)
                                ->label(__('catalog.long_description')),
                        ]),
                    Tab::make(__('catalog.language_arabic'))
                        ->schema([
                            TextInput::make('name.ar')
                                ->markAsRequired()
                                ->rule('required')
                                ->maxLength(255)
                                ->label(__('catalog.name')),
                            Textarea::make('short_description.ar')
                                ->markAsRequired()
                                ->rule('required')
                                ->maxLength(1000)
                                ->rows(3)
                                ->label(__('catalog.short_description')),
                            Textarea::make('long_description.ar')
                                ->maxLength(5000)
                                ->rows(5)
                                ->label(__('catalog.long_description')),
                        ]),
                ])
                ->columnSpanFull(),

            Section::make(__('catalog.shared'))
                ->schema([
                    Select::make('vendor_profile_id')
                        ->relationship('vendor', 'id')
                        ->getOptionLabelFromRecordUsing(fn ($record) => app(StorefrontText::class)->translation($record, 'business_name'))
                        ->searchable()
                        ->preload()
                        ->markAsRequired()
                        ->rule('required')
                        ->label(__('catalog.vendor')),
                    Select::make('category_id')
                        ->relationship('category', 'id')
                        ->getOptionLabelFromRecordUsing(fn ($record): string => $record->getTranslation('name', app()->getLocale(), useFallbackLocale: true))
                        ->searchable()
                        ->optionsLimit(50)
                        ->markAsRequired()
                        ->rule('required')
                        ->label(__('catalog.category')),
                    TextInput::make('base_price_minor')
                        ->label(__('catalog.base_price'))
                        ->helperText(__('catalog.price_helper'))
                        ->numeric()
                        ->minValue(0)
                        ->markAsRequired()
                        ->rule('required'),
                    Select::make('status')
                        ->options(ServiceStatus::class)
                        ->default(ServiceStatus::Draft->value)
                        ->disabled()
                        ->dehydrated(false)
                        ->label(__('catalog.status_label')),
                    Toggle::make('is_featured')
                        ->label(__('catalog.is_featured')),
                ])
                ->columns(2),

            Section::make(__('catalog.sale_details'))
                ->relationship('saleDetail')
                ->schema([
                    Toggle::make('is_perishable')
                        ->label(__('catalog.is_perishable')),
                    Toggle::make('is_made_to_order')
                        ->label(__('catalog.is_made_to_order'))
                        ->live(),
                    TextInput::make('lead_time_hours')
                        ->numeric()
                        ->minValue(1)
                        ->label(__('catalog.lead_time_hours'))
                        ->visible(fn ($get): bool => (bool) $get('is_made_to_order')),
                    TextInput::make('stock_quantity')
                        ->numeric()
                        ->minValue(0)
                        ->label(__('catalog.stock_quantity'))
                        ->helperText(__('catalog.stock_quantity_hint')),
                    Repeater::make('customization_fields')
                        ->label(__('catalog.customization_fields'))
                        ->schema([
                            TextInput::make('key')
                                ->label(__('catalog.customization_field_key'))
                                ->required(),
                            Select::make('type')
                                ->label(__('catalog.customization_field_type'))
                                ->options([
                                    'text' => __('catalog.customization_types.text'),
                                    'number' => __('catalog.customization_types.number'),
                                    'select' => __('catalog.customization_types.select'),
                                    'boolean' => __('catalog.customization_types.boolean'),
                                ])
                                ->required(),
                        ])
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Section::make(__('catalog.media'))
                ->schema([
                    SpatieMediaLibraryFileUpload::make('gallery')
                        ->label(__('catalog.gallery'))
                        ->collection('gallery')
                        ->multiple()
                        ->maxFiles(11)
                        ->image()
                        ->reorderable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                ImageColumn::make('cover_image')
                    ->label(__('catalog.cover_image'))
                    ->size(48)
                    ->square()
                    ->defaultImageUrl(asset('images/service-placeholder.svg'))
                    ->getStateUsing(fn (Service $record): ?string => rescue(
                        fn () => $record->getFirstMediaUrl('gallery', 'thumb') ?: null,
                        null,
                        false,
                    )),
                TextColumn::make('name')
                    ->getStateUsing(fn (Service $record): string => $record->getTranslation('name', app()->getLocale(), useFallbackLocale: true))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query
                        ->where('name->en', 'like', "%{$search}%")
                        ->orWhere('name->ar', 'like', "%{$search}%"))
                    ->limit(40)
                    ->label(__('catalog.name')),
                TextColumn::make('public_id')
                    ->label(__('catalog.public_id'))
                    ->copyable()
                    ->searchable()
                    ->limit(12)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('product_type')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (ProductType $state) => $state->label())
                    ->label(__('catalog.product_type')),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (mixed $state): string => ServiceStatus::tryFrom(
                        $state instanceof ServiceState
                            ? $state->getValue()
                            : ($state instanceof ServiceStatus ? $state->value : (is_string($state) ? $state : '')),
                    )?->color() ?? 'gray')
                    ->formatStateUsing(fn (mixed $state): string => ServiceStatus::tryFrom(
                        $state instanceof ServiceState
                            ? $state->getValue()
                            : ($state instanceof ServiceStatus ? $state->value : (is_string($state) ? $state : '')),
                    )?->label() ?? '—')
                    ->label(__('catalog.status_label')),
                TextColumn::make('base_price_minor')
                    ->money('EGP', divideBy: 100, locale: app()->getLocale())
                    ->sortable()
                    ->label(__('catalog.base_price')),
                TextColumn::make('vendor.business_name')
                    ->getStateUsing(fn (Service $record): string => $record->vendor === null
                        ? ''
                        : app(StorefrontText::class)->translation($record->vendor, 'business_name'))
                    ->label(__('catalog.vendor'))
                    ->searchable(false),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(ServiceStatus::class),
                TrashedFilter::make(),
            ])
            ->headerActions([
                ReindexServicesAction::make(),
                Action::make('importSaleServices')
                    ->label(__('catalog.import.sale_label'))
                    ->icon('heroicon-o-arrow-up-tray')
                    ->form([
                        Select::make('vendor_profile_id')
                            ->label(__('catalog.import.vendor'))
                            ->options(
                                fn (): array => VendorProfile::query()
                                    ->where('approval_status', 'approved')
                                    ->get()
                                    ->mapWithKeys(fn (VendorProfile $vp): array => [
                                        $vp->id => app(StorefrontText::class)->translation($vp, 'business_name', 'en'),
                                    ])
                                    ->all()
                            )
                            ->searchable()
                            ->required(),
                        FileUpload::make('file')
                            ->label(__('catalog.import.file'))
                            ->acceptedFileTypes([
                                'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                                'application/vnd.ms-excel',
                            ])
                            ->disk('local')
                            ->directory('excel-imports-temp')
                            ->required(),
                    ])
                    ->action(function (array $data, ImportSaleServicesFromExcelAction $action): void {
                        $absolutePath = Storage::disk('local')->path($data['file']);
                        $file = new UploadedFile($absolutePath, basename($absolutePath), null, null, true);

                        $import = $action->execute($file, (int) $data['vendor_profile_id'], app()->getLocale());

                        if ($import->status === 'completed') {
                            Notification::make()
                                ->title(__('catalog.import.completed', ['count' => $import->imported_rows]))
                                ->success()
                                ->send();
                        } else {
                            Notification::make()
                                ->title(__('catalog.import.failed_row_errors'))
                                ->danger()
                                ->send();
                        }
                    })
                    ->modalHeading(__('catalog.import.modal_heading_sale'))
                    ->modalSubmitActionLabel(__('catalog.import.modal_submit'))
                    ->requiresConfirmation(false),
            ])
            ->actions([
                ActionGroup::make([
                    ApproveServiceAction::make(),
                    RejectServiceAction::make(),
                    RequestServiceEditsAction::make(),
                    ViewAction::make(),
                    EditAction::make(),
                    DeleteAction::make(),
                    ForceDeleteAction::make(),
                    RestoreAction::make(),
                ])
                    ->label(__('admin.actions.review'))
                    ->button()
                    ->color('primary'),
            ])
            ->bulkActions([
                BulkApproveServicesAction::make(),
                BulkRejectServicesAction::make(),
                BulkArchiveServicesAction::make(),
            ])
            ->emptyStateIcon('heroicon-o-shopping-bag')
            ->emptyStateHeading(__('catalog.sale_empty_heading'))
            ->emptyStateDescription(__('catalog.sale_empty_description'));
    }

    public static function infolist(Infolist $schema): Infolist
    {
        return $schema->components([
            AuditTimelineSection::make()
                ->audience(TimelineAudience::Admin)
                ->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSaleServices::route('/'),
            'pending' => PendingSaleServicesPage::route('/pending-review'),
            'create' => CreateSaleService::route('/create'),
            'view' => ViewSaleService::route('/{record}'),
            'edit' => EditSaleService::route('/{record}/edit'),
        ];
    }
}
