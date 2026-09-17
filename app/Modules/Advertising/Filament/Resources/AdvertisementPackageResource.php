<?php

declare(strict_types=1);

namespace App\Modules\Advertising\Filament\Resources;

use App\Modules\Advertising\Domain\Enums\PlacementType;
use App\Modules\Advertising\Domain\Models\AdvertisementPackage;
use App\Modules\Advertising\Filament\Resources\AdvertisementPackageResource\Pages\CreateAdvertisementPackage;
use App\Modules\Advertising\Filament\Resources\AdvertisementPackageResource\Pages\EditAdvertisementPackage;
use App\Modules\Advertising\Filament\Resources\AdvertisementPackageResource\Pages\ListAdvertisementPackages;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class AdvertisementPackageResource extends Resource
{
    use Translatable;

    protected static ?string $model = AdvertisementPackage::class;

    protected static ?string $navigationIcon = 'heroicon-o-megaphone';

    protected static ?int $navigationSort = 20;

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.advertising');
    }

    public static function getModelLabel(): string
    {
        return __('advertising.package');
    }

    public static function getPluralModelLabel(): string
    {
        return __('advertising.packages');
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Tabs::make('Translations')
                ->tabs([
                    Tab::make('English')
                        ->schema([
                            TextInput::make('name.en')
                                ->label('Name (EN)')
                                ->required()
                                ->maxLength(255),
                            Textarea::make('description.en')
                                ->label('Description (EN)')
                                ->rows(3),
                        ]),
                    Tab::make('العربية')
                        ->schema([
                            TextInput::make('name.ar')
                                ->label('Name (AR)')
                                ->required()
                                ->maxLength(255),
                            Textarea::make('description.ar')
                                ->label('Description (AR)')
                                ->rows(3),
                        ]),
                ])
                ->columnSpanFull(),

            Section::make(__('advertising.package_details'))
                ->schema([
                    Select::make('placement_type')
                        ->label(__('advertising.placement_type'))
                        ->options(collect(PlacementType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                        ->required(),

                    TextInput::make('duration_days')
                        ->label(__('advertising.duration_days'))
                        ->required()
                        ->numeric()
                        ->minValue(1)
                        ->suffix(__('advertising.days')),

                    TextInput::make('price_minor')
                        ->label(__('advertising.price'))
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->suffix('EGP (piastres)'),

                    TextInput::make('impression_limit')
                        ->label(__('advertising.impression_limit'))
                        ->numeric()
                        ->minValue(1)
                        ->placeholder(__('advertising.unlimited')),

                    Toggle::make('is_active')
                        ->label(__('advertising.is_active'))
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('advertising.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('placement_type')
                    ->label(__('advertising.placement_type'))
                    ->badge()
                    ->color(fn (PlacementType $state): string => $state->color())
                    ->formatStateUsing(fn (PlacementType $state): string => $state->label()),
                TextColumn::make('duration_days')
                    ->label(__('advertising.duration_days'))
                    ->suffix(' '.__('advertising.days'))
                    ->sortable(),
                TextColumn::make('price_minor')
                    ->label(__('advertising.price'))
                    ->money('EGP', divideBy: 100)
                    ->sortable(),
                TextColumn::make('impression_limit')
                    ->label(__('advertising.impression_limit'))
                    ->placeholder('∞'),
                IconColumn::make('is_active')
                    ->label(__('advertising.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('placement_type')
                    ->options(collect(PlacementType::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                    ->label(__('advertising.placement_type')),
                TernaryFilter::make('is_active')
                    ->label(__('advertising.is_active')),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAdvertisementPackages::route('/'),
            'create' => CreateAdvertisementPackage::route('/create'),
            'edit' => EditAdvertisementPackage::route('/{record}/edit'),
        ];
    }
}
