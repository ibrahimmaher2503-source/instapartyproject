<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Resources;

use App\Modules\Catalog\Application\Actions\CloneServiceAction;
use App\Modules\Catalog\Application\Actions\SubmitServiceForReviewAction;
use App\Modules\Catalog\Application\Actions\VendorArchiveServiceAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\ChangesRequestedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\DraftState;
use App\Modules\Catalog\Domain\States\ServiceStatus\PublishedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\RejectedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\ServiceState;
use App\Modules\Catalog\Filament\Vendor\Concerns\HasCategoryOccasionHelper;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource\Pages\CreateVendorSaleService;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource\Pages\EditVendorSaleService;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource\Pages\ListVendorSaleServices;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Forms\Components\Grid;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Throwable;

class VendorSaleServiceResource extends Resource
{
    use HasCategoryOccasionHelper;

    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationGroup = 'services';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return __('vendor-portal.services.sale_services');
    }

    public static function getModelLabel(): string
    {
        return __('catalog.models.sale_service.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.models.sale_service.plural');
    }

    public static function getTitle(): string|Htmlable
    {
        return __('catalog.models.sale_service.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        $vendorProfile = self::getVendorProfile();

        return parent::getEloquentQuery()
            ->where('vendor_profile_id', $vendorProfile->id)
            ->where('product_type', ProductType::Sale);
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Tabs::make(__('catalog.translations'))
                ->tabs([
                    Tab::make(__('catalog.language_english'))
                        ->schema([
                            TextInput::make('name.en')
                                ->label(__('catalog.name_en'))
                                ->required()
                                ->maxLength(255),
                            Textarea::make('short_description.en')
                                ->label(__('catalog.short_description').' (EN)')
                                ->rows(3)
                                ->maxLength(500),
                            Textarea::make('long_description.en')
                                ->label(__('catalog.long_description').' (EN)')
                                ->rows(5),
                        ]),
                    Tab::make(__('catalog.language_arabic'))
                        ->schema([
                            TextInput::make('name.ar')
                                ->label(__('catalog.name_ar'))
                                ->required()
                                ->maxLength(255),
                            Textarea::make('short_description.ar')
                                ->label(__('catalog.short_description').' (AR)')
                                ->rows(3)
                                ->maxLength(500),
                            Textarea::make('long_description.ar')
                                ->label(__('catalog.long_description').' (AR)')
                                ->rows(5),
                        ]),
                ])
                ->columnSpanFull(),

            Section::make(__('catalog.shared'))
                ->schema([
                    Select::make('category_id')
                        ->label(__('catalog.category'))
                        ->options(function () {
                            return Category::query()
                                ->where('is_active', true)
                                ->whereJsonContains('allowed_product_types', ProductType::Sale->value)
                                ->orderBy('sort_order')
                                ->get()
                                ->mapWithKeys(fn (Category $c) => [
                                    $c->id => $c->getTranslation('name', app()->getLocale()),
                                ]);
                        })
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->helperText(fn (Get $get): ?string => static::categoryOccasionHelper($get)),
                    TextInput::make('base_price_minor')
                        ->label(__('catalog.base_price'))
                        ->numeric()
                        ->required()
                        ->minValue(0)
                        ->suffix(__('catalog.piastres_suffix')),
                ])
                ->columns(2),

            Section::make(__('catalog.sale_details'))
                ->schema([
                    Grid::make(2)->schema([
                        Toggle::make('saleDetail.is_perishable')
                            ->label(__('catalog.is_perishable'))
                            ->default(false),
                        Toggle::make('saleDetail.is_made_to_order')
                            ->label(__('catalog.is_made_to_order'))
                            ->default(false),
                    ]),
                    Grid::make(2)->schema([
                        TextInput::make('saleDetail.lead_time_hours')
                            ->label(__('catalog.lead_time_hours'))
                            ->numeric()
                            ->minValue(0),
                        TextInput::make('saleDetail.stock_quantity')
                            ->label(__('catalog.stock_quantity'))
                            ->numeric()
                            ->minValue(0)
                            ->hint(__('catalog.stock_quantity_hint')),
                    ]),
                ]),

            Section::make(__('catalog.media'))
                ->schema([
                    SpatieMediaLibraryFileUpload::make('gallery')
                        ->label(__('catalog.gallery'))
                        ->collection('gallery')
                        ->multiple()
                        ->maxFiles(11)
                        ->image()
                        ->reorderable()
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('gallery')
                    ->circular()
                    ->getStateUsing(fn (Service $record): ?string => rescue(
                        fn (): ?string => $record->getFirstMediaUrl('gallery', 'thumb') ?: null,
                        null,
                        false,
                    )),
                TextColumn::make('name')
                    ->label(__('catalog.name'))
                    ->formatStateUsing(fn (Service $record) => $record->getTranslation('name', app()->getLocale()))
                    ->searchable()
                    ->limit(40),
                TextColumn::make('public_id')
                    ->label(__('catalog.public_id'))
                    ->copyable()
                    ->searchable()
                    ->limit(12)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('status')
                    ->label(__('catalog.status_label'))
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
                    )?->label() ?? '—'),
                TextColumn::make('base_price_minor')
                    ->label(__('catalog.base_price'))
                    ->money('EGP', divideBy: 100),
                TextColumn::make('updated_at')
                    ->label(__('vendor-portal.services.last_modified'))
                    ->dateTime('d M Y')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('submitForReview')
                    ->label(__('vendor-portal.services.submit_for_review'))
                    ->icon('heroicon-o-paper-airplane')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->visible(fn (Service $record): bool => $record->status instanceof DraftState ||
                        $record->status instanceof ChangesRequestedState)
                    ->action(function (Service $record): void {
                        app(SubmitServiceForReviewAction::class)->execute($record, self::getVendorProfile());
                        Notification::make()->title(__('vendor-portal.services.submitted'))->success()->send();
                    }),

                Action::make('clone')
                    ->label(__('vendor-portal.services.clone'))
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (Service $record): void {
                        $clone = app(CloneServiceAction::class)->execute($record, self::getVendorProfile());
                        Notification::make()->title(__('vendor-portal.services.cloned'))->success()->send();
                        redirect()->to(static::getUrl('edit', ['record' => $clone]));
                    }),

                Action::make('archive')
                    ->label(__('vendor-portal.services.archive'))
                    ->icon('heroicon-o-archive-box')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Service $record): bool => $record->status instanceof PublishedState ||
                        $record->status instanceof RejectedState)
                    ->action(function (Service $record): void {
                        try {
                            app(VendorArchiveServiceAction::class)->execute($record, self::getVendorProfile());
                            Notification::make()->title(__('vendor-portal.services.archived'))->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();
                        }
                    }),

                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVendorSaleServices::route('/'),
            'create' => CreateVendorSaleService::route('/create'),
            'edit' => EditVendorSaleService::route('/{record}/edit'),
        ];
    }

    public static function canAccess(): bool
    {
        $vendor = auth()->user()?->vendorProfile;

        return $vendor && $vendor->approvedTypes()
            ->where('product_type', ProductType::Sale->value)
            ->exists();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    protected static function getVendorProfile(): VendorProfile
    {
        return auth()->user()->vendorProfile;
    }
}
