<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServicePackage;
use App\Modules\Catalog\Filament\Resources\ServicePackageResource\Pages\CreateServicePackage;
use App\Modules\Catalog\Filament\Resources\ServicePackageResource\Pages\EditServicePackage;
use App\Modules\Catalog\Filament\Resources\ServicePackageResource\Pages\ListServicePackages;
use App\Modules\Shared\Application\Services\StorefrontText;
use Brick\Money\Money;
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
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rule;

class ServicePackageResource extends Resource
{
    use Translatable;

    protected static ?string $model = ServicePackage::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?int $navigationSort = 45;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.catalog');
    }

    public static function getNavigationLabel(): string
    {
        return __('catalog.nav.service_packages');
    }

    public static function getModelLabel(): string
    {
        return __('catalog.models.service_package.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.models.service_package.plural');
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function eligibleServices(?Builder $query = null): Builder
    {
        $types = static::permittedTypes(['create']);
        $query ??= Service::query();

        return $types === []
            ? $query->whereRaw('1 = 0')
            : static::applyServiceEligibility($query->whereIn('product_type', $types));
    }

    /** @param list<string> $actions
     * @return list<string>
     */
    private static function permittedTypes(array $actions): array
    {
        $user = auth()->user();
        if ($user === null) {
            return [];
        }

        return collect(ProductType::cases())
            ->filter(fn (ProductType $type): bool => collect($actions)
                ->contains(fn (string $action): bool => $user->can("{$action}_{$type->value}::service", 'web')))
            ->map(fn (ProductType $type): string => $type->value)
            ->values()
            ->all();
    }

    private static function applyServiceEligibility(Builder $query): Builder
    {
        return $query
            ->displayable()
            ->whereHas('vendor', fn (Builder $vendors) => $vendors->approved()
                ->whereHas('approvedTypes', fn (Builder $types) => $types
                    ->whereColumn('vendor_approved_product_types.product_type', 'services.product_type')));
    }

    public static function getEloquentQuery(): Builder
    {
        $types = static::permittedTypes(['view', 'view_any', 'create', 'update']);

        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['service.vendor'])
            ->whereHas('service', fn (Builder $services) => $services->whereIn('product_type', $types));
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Section::make(__('catalog.service_package_details'))
                ->schema([
                    Select::make('service_id')
                        ->label(__('catalog.service'))
                        ->relationship('service', 'slug', modifyQueryUsing: fn (Builder $query) => static::eligibleServices($query))
                        ->getOptionLabelFromRecordUsing(fn (Service $record): string => app(StorefrontText::class)->translation($record, 'name').' · '.app(StorefrontText::class)->translation($record->vendor, 'business_name').' · '.strtoupper($record->product_type->value))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (Set $set, mixed $state): void {
                            $set('currency', is_numeric($state) ? Service::query()->whereKey($state)->value('base_price_currency') : null);
                        })
                        ->disabledOn('edit')
                        ->rule(function (Get $get): \Closure {
                            return function (string $attribute, mixed $value, \Closure $fail): void {
                                $service = is_numeric($value)
                                    ? Service::query()->with('vendor.approvedTypes')->find($value)
                                    : null;

                                if ($service === null || ! static::serviceIsEligible($service, ['create', 'update'])) {
                                    $fail(__('catalog.package_service_ineligible'));
                                }
                            };
                        }),
                    TextInput::make('code')->label(__('catalog.code'))->required()->maxLength(80)
                        ->rule(fn (Get $get, ?ServicePackage $record) => Rule::unique('service_packages', 'code')
                            ->where('service_id', $record?->service_id ?? $get('service_id'))
                            ->ignore($record?->id)),
                    TextInput::make('price_minor')->label(__('catalog.package_price_minor'))->numeric()->integer()->minValue(1)->required(),
                    TextInput::make('currency')->label(__('catalog.currency'))->disabled()->dehydrated(false),
                    Toggle::make('is_active')->label(__('catalog.is_active'))->default(true),
                ])->columns(2),
            Tabs::make(__('catalog.translations'))->tabs([
                Tab::make(__('catalog.language_english'))->schema([
                    TextInput::make('name.en')->label(__('catalog.name'))->required(false)->rule('required')->markAsRequired()->maxLength(255),
                    Textarea::make('description.en')->label(__('catalog.description'))->required(false)->rule('required')->markAsRequired()->maxLength(2000)->rows(4),
                ]),
                Tab::make(__('catalog.language_arabic'))->schema([
                    TextInput::make('name.ar')->label(__('catalog.name'))->required(false)->rule('required')->markAsRequired()->maxLength(255)->extraInputAttributes(['dir' => 'rtl']),
                    Textarea::make('description.ar')->label(__('catalog.description'))->required(false)->rule('required')->markAsRequired()->maxLength(2000)->rows(4)->extraInputAttributes(['dir' => 'rtl']),
                ]),
            ])->columnSpanFull(),
            Section::make(__('catalog.package_components'))->schema([
                Repeater::make('components')->relationship()->label(__('catalog.package_components'))->required()->minItems(1)->reorderable()
                    ->schema([
                        TextInput::make('name.en')->label(__('catalog.name_en'))->required()->maxLength(255),
                        TextInput::make('name.ar')->label(__('catalog.name_ar'))->required()->maxLength(255)->extraInputAttributes(['dir' => 'rtl']),
                        Textarea::make('description.en')->label(__('catalog.description_en'))->required()->maxLength(1000),
                        Textarea::make('description.ar')->label(__('catalog.description_ar'))->required()->maxLength(1000)->extraInputAttributes(['dir' => 'rtl']),
                        TextInput::make('quantity')->label(__('catalog.quantity'))->numeric()->integer()->minValue(1)->maxValue(999)->required(),
                        TextInput::make('sort_order')->label(__('catalog.sort_order'))->numeric()->integer()->minValue(0)->maxValue(65535)->default(0),
                    ])->columns(2),
            ])->columnSpanFull(),
            Section::make(__('catalog.package_gallery'))->schema([
                SpatieMediaLibraryFileUpload::make('gallery')
                    ->label(__('catalog.gallery'))
                    ->collection('gallery')
                    ->disk('s3-public')
                    ->image()
                    ->multiple()
                    ->reorderable()
                    ->maxFiles(6)
                    ->maxSize(5120)
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->modifyQueryUsing(fn (Builder $query) => $query
            ->withoutGlobalScopes([SoftDeletingScope::class])
            ->with(['service.vendor']))
            ->columns([
                TextColumn::make('name')->label(__('catalog.name'))
                    ->getStateUsing(fn (ServicePackage $record): string => $record->getTranslation('name', app()->getLocale(), true))
                    ->searchable(),
                TextColumn::make('service.name')->label(__('catalog.service'))
                    ->getStateUsing(fn (ServicePackage $record): string => $record->service?->getTranslation('name', app()->getLocale(), true) ?? '—')
                    ->searchable(),
                TextColumn::make('service.vendor.business_name')->label(__('catalog.vendor'))
                    ->getStateUsing(fn (ServicePackage $record): string => $record->service?->vendor === null ? '—' : app(StorefrontText::class)->translation($record->service->vendor, 'business_name')),
                TextColumn::make('price_minor')->label(__('catalog.package_price_minor'))
                    ->formatStateUsing(fn (ServicePackage $record): string => Money::ofMinor($record->price_minor, $record->currency)->formatTo(app()->getLocale() === 'ar' ? 'ar_EG' : 'en_GB')),
                IconColumn::make('is_active')->label(__('catalog.is_active'))->boolean(),
                TextColumn::make('deleted_at')->label(__('catalog.deleted_at'))->dateTime()->toggleable(isToggledHiddenByDefault: true),
            ])->filters([TrashedFilter::make()])
            ->actions([EditAction::make(), DeleteAction::make()])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServicePackages::route('/'),
            'create' => CreateServicePackage::route('/create'),
            'edit' => EditServicePackage::route('/{record}/edit'),
        ];
    }

    /** @param list<string> $actions */
    public static function serviceIsEligible(Service $service, array $actions = ['create']): bool
    {
        return $service->status->getValue() === 'published'
            && (config('catalog.digital_services_enabled', true) || $service->product_type !== ProductType::Digital)
            && collect($actions)->contains(fn (string $action): bool => auth()->user()?->can("{$action}_{$service->product_type->value}::service", 'web') === true)
            && $service->vendor !== null
            && $service->vendor->approval_status->getValue() === 'approved'
            && $service->vendor->approvedTypes->contains(fn ($approvedType): bool => $approvedType->product_type === $service->product_type);
    }
}
