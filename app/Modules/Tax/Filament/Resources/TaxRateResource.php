<?php

declare(strict_types=1);

namespace App\Modules\Tax\Filament\Resources;

use App\Modules\Tax\Domain\Enums\TaxAppliesTo;
use App\Modules\Tax\Domain\Models\TaxRate;
use App\Modules\Tax\Filament\Resources\TaxRateResource\Pages\CreateTaxRate;
use App\Modules\Tax\Filament\Resources\TaxRateResource\Pages\EditTaxRate;
use App\Modules\Tax\Filament\Resources\TaxRateResource\Pages\ListTaxRates;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
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
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class TaxRateResource extends Resource
{
    use Translatable;

    protected static ?string $model = TaxRate::class;

    protected static ?string $navigationIcon = 'heroicon-o-receipt-percent';

    protected static ?int $navigationSort = 20;

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.tax');
    }

    public static function getModelLabel(): string
    {
        return __('tax.rate');
    }

    public static function getPluralModelLabel(): string
    {
        return __('tax.rates');
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

            Section::make(__('tax.rate_settings'))
                ->schema([
                    TextInput::make('rate_bps')
                        ->label(__('tax.rate_percent'))
                        ->required()
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100_00)
                        ->suffix('%')
                        ->formatStateUsing(fn (?int $state): ?float => $state !== null ? $state / 100 : null)
                        ->dehydrateStateUsing(fn (?float $state): ?int => $state !== null ? (int) round($state * 100) : null),

                    Select::make('applies_to')
                        ->label(__('tax.applies_to'))
                        ->options(collect(TaxAppliesTo::cases())->mapWithKeys(fn ($c) => [$c->value => $c->label()]))
                        ->required(),

                    CheckboxList::make('product_types')
                        ->label(__('tax.product_types'))
                        ->options([
                            'rental' => __('catalog.product_types.rental'),
                            'sale' => __('catalog.product_types.sale'),
                            'digital' => __('catalog.product_types.digital'),
                        ])
                        ->helperText(__('tax.product_types_hint')),

                    Toggle::make('is_tax_inclusive')
                        ->label(__('tax.is_tax_inclusive')),

                    Toggle::make('is_active')
                        ->label(__('tax.is_active'))
                        ->default(true),
                ])
                ->columns(2),

            Section::make(__('tax.effective_period'))
                ->schema([
                    DatePicker::make('effective_from')
                        ->label(__('tax.effective_from'))
                        ->required()
                        ->native(false),
                    DatePicker::make('effective_to')
                        ->label(__('tax.effective_to'))
                        ->native(false)
                        ->afterOrEqual('effective_from'),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('tax.name'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('rate_bps')
                    ->label(__('tax.rate_percent'))
                    ->formatStateUsing(fn (int $state): string => number_format($state / 100, 2).'%')
                    ->sortable(),
                TextColumn::make('applies_to')
                    ->label(__('tax.applies_to'))
                    ->badge()
                    ->formatStateUsing(fn (TaxAppliesTo $state): string => $state->label()),
                IconColumn::make('is_active')
                    ->label(__('tax.is_active'))
                    ->boolean(),
                TextColumn::make('effective_from')
                    ->label(__('tax.effective_from'))
                    ->date()
                    ->sortable(),
                TextColumn::make('effective_to')
                    ->label(__('tax.effective_to'))
                    ->date()
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('tax.is_active')),
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
            'index' => ListTaxRates::route('/'),
            'create' => CreateTaxRate::route('/create'),
            'edit' => EditTaxRate::route('/{record}/edit'),
        ];
    }
}
