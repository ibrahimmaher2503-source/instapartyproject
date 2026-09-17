<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources;

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
use App\Modules\Catalog\Filament\Resources\RentalServiceResource\Pages\CreateRentalService;
use App\Modules\Catalog\Filament\Resources\RentalServiceResource\Pages\EditRentalService;
use App\Modules\Catalog\Filament\Resources\RentalServiceResource\Pages\ListRentalServices;
use App\Modules\Catalog\Filament\Resources\RentalServiceResource\Pages\PendingRentalServicesPage;
use App\Modules\Discovery\Filament\Actions\ReindexServicesAction;
use App\Modules\Shared\Application\Services\StorefrontText;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Filament\Infolists\Components\AuditTimelineSection;
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
use Filament\Resources\Resource;
use Filament\Tables\Actions\ActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\ForceDeleteAction;
use Filament\Tables\Actions\RestoreAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class RentalServiceResource extends Resource
{
    protected static ?string $model = Service::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.services');
    }

    protected static ?int $navigationSort = 10;

    public static function getNavigationLabel(): string
    {
        return __('catalog.nav.rental_services');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = Service::query()
            ->forType(ProductType::Rental)
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
        return __('catalog.models.rental_service.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.models.rental_service.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->where('product_type', ProductType::Rental);
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

            Section::make(__('catalog.rental_details'))
                ->relationship('rentalDetail')
                ->schema([
                    Toggle::make('requires_electricity')
                        ->label(__('catalog.requires_electricity')),
                    Toggle::make('requires_outdoor_space')
                        ->label(__('catalog.requires_outdoor_space')),
                    TextInput::make('default_rental_duration_hours')
                        ->numeric()
                        ->minValue(1)
                        ->markAsRequired()
                        ->rule('required')
                        ->label(__('catalog.default_rental_duration_hours')),
                    TextInput::make('setup_time_minutes')
                        ->numeric()
                        ->minValue(0)
                        ->label(__('catalog.setup_time_minutes')),
                    TextInput::make('teardown_time_minutes')
                        ->numeric()
                        ->minValue(0)
                        ->label(__('catalog.teardown_time_minutes')),
                    TextInput::make('security_deposit_minor')
                        ->label(__('catalog.security_deposit'))
                        ->helperText(__('catalog.price_helper'))
                        ->numeric()
                        ->minValue(0),
                    TextInput::make('minimum_space_sqm')
                        ->numeric()
                        ->minValue(1)
                        ->label(__('catalog.minimum_space_sqm')),
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
            ])
            ->actions([
                ActionGroup::make([
                    ApproveServiceAction::make(),
                    RejectServiceAction::make(),
                    RequestServiceEditsAction::make(),
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
            ->emptyStateIcon('heroicon-o-cube')
            ->emptyStateHeading(__('catalog.rental_empty_heading'))
            ->emptyStateDescription(__('catalog.rental_empty_description'));
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
            'index' => ListRentalServices::route('/'),
            'pending' => PendingRentalServicesPage::route('/pending-review'),
            'create' => CreateRentalService::route('/create'),
            'edit' => EditRentalService::route('/{record}/edit'),
        ];
    }
}
