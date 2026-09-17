<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Filament\Resources;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Settlement\Domain\Models\CommissionRate;
use App\Modules\Settlement\Filament\Resources\CommissionRulesResource\Pages\CreateCommissionRule;
use App\Modules\Settlement\Filament\Resources\CommissionRulesResource\Pages\EditCommissionRule;
use App\Modules\Settlement\Filament\Resources\CommissionRulesResource\Pages\ListCommissionRules;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class CommissionRulesResource extends Resource
{
    protected static ?string $model = CommissionRate::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settlement');
    }

    protected static ?string $navigationIcon = 'heroicon-o-percent-badge';

    protected static ?string $recordTitleAttribute = 'public_id';

    protected static ?string $slug = 'settlement-commission-rules';

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('settlement.nav.commission_rules');
    }

    public static function getModelLabel(): string
    {
        return __('settlement.models.commission_rule.singular');
    }

    private static function categoryLabel($record): string
    {
        $locale = app()->getLocale();
        $value = $record->getTranslation('name', $locale, false)
            ?: $record->getTranslation('name', 'en', false);

        while (is_array($value)) {
            $value = $value[$locale] ?? $value['en'] ?? reset($value);
        }

        $value = (string) ($value ?? '');

        return $value !== '' ? $value : ('#'.$record->id);
    }

    public static function getPluralModelLabel(): string
    {
        return __('settlement.models.commission_rule.plural');
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Section::make(__('settlement.commission_rule.section_heading'))
                ->schema([
                    Select::make('category_id')
                        ->label(__('settlement.commission_rule.category_label'))
                        ->relationship('category', 'id')
                        ->getOptionLabelFromRecordUsing(fn ($record): string => self::categoryLabel($record))
                        ->getSearchResultsUsing(fn (string $search) => Category::query()
                            ->where('name->en', 'like', "%{$search}%")
                            ->orWhere('name->ar', 'like', "%{$search}%")
                            ->limit(50)
                            ->get()
                            ->mapWithKeys(fn ($c) => [$c->id => self::categoryLabel($c)])
                            ->all())
                        ->searchable()
                        ->preload()
                        ->placeholder(__('settlement.commission_rule.category_placeholder'))
                        ->nullable(),

                    Select::make('product_type')
                        ->label(__('settlement.commission_rule.product_type_label'))
                        ->options([
                            ProductType::Rental->value => __('settlement.product_type_options.rental'),
                            ProductType::Sale->value => __('settlement.product_type_options.sale'),
                            ProductType::Digital->value => __('settlement.product_type_options.digital'),
                        ])
                        ->placeholder(__('settlement.commission_rule.product_type_placeholder'))
                        ->nullable(),

                    TextInput::make('commission_bps')
                        ->label(__('settlement.commission_rule.commission_bps_label'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(10000)
                        ->required()
                        ->helperText(__('settlement.commission_rule.commission_bps_helper')),

                    DatePicker::make('effective_from')
                        ->label(__('settlement.commission_rule.effective_from_label'))
                        ->required()
                        ->default(today())
                        ->native(false),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('effective_from', 'desc')
            ->columns([
                TextColumn::make('category.id')
                    ->label(__('settlement.columns.category'))
                    ->formatStateUsing(fn ($record) => $record->category
                        ? $record->category->getTranslation('name', app()->getLocale()) ?: $record->category->getTranslation('name', 'en')
                        : __('settlement.commission_rule.category_any'))
                    ->default(__('settlement.commission_rule.category_any'))
                    ->searchable(),

                TextColumn::make('product_type')
                    ->badge()
                    ->color(fn (?ProductType $state): string => $state ? match ($state) {
                        ProductType::Rental => 'warning',
                        ProductType::Sale => 'success',
                        ProductType::Digital => 'info',
                    } : 'gray')
                    ->formatStateUsing(fn (?ProductType $state): string => $state ? $state->label() : __('settlement.commission_rule.category_any'))
                    ->label(__('settlement.columns.product_type')),

                TextColumn::make('commission_bps')
                    ->label(__('settlement.columns.basis_points'))
                    ->formatStateUsing(fn (int $state): string => $state.' bps ('.number_format($state / 100, 2).'%)')
                    ->sortable(),

                TextColumn::make('effective_from')
                    ->date()
                    ->label(__('settlement.columns.effective_from'))
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->label(__('admin.common.created_at'))
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('product_type')
                    ->options([
                        ProductType::Rental->value => __('settlement.product_type_options.rental'),
                        ProductType::Sale->value => __('settlement.product_type_options.sale'),
                        ProductType::Digital->value => __('settlement.product_type_options.digital'),
                    ])
                    ->label(__('settlement.columns.product_type')),
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
            'index' => ListCommissionRules::route('/'),
            'create' => CreateCommissionRule::route('/create'),
            'edit' => EditCommissionRule::route('/{record}/edit'),
        ];
    }
}
