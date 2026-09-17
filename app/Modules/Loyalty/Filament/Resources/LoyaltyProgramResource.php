<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources;

use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource\Pages\CreateLoyaltyProgram;
use App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource\Pages\EditLoyaltyProgram;
use App\Modules\Loyalty\Filament\Resources\LoyaltyProgramResource\Pages\ListLoyaltyPrograms;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoyaltyProgramResource extends Resource
{
    protected static ?string $model = LoyaltyProgram::class;

    protected static ?string $navigationIcon = 'heroicon-o-star';

    protected static ?int $navigationSort = 10;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.loyalty');
    }

    public static function getNavigationLabel(): string
    {
        return __('loyalty.nav.programs');
    }

    public static function getModelLabel(): string
    {
        return __('loyalty.models.program.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty.models.program.plural');
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['vendor']);

        if (auth()->check() && ! auth()->user()->hasRole('super_admin')) {
            $vendorProfileId = auth()->user()->vendorProfile?->id;
            if ($vendorProfileId) {
                $query->where('vendor_profile_id', $vendorProfileId);
            }
        }

        return $query;
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Tabs::make('Translations')
                ->tabs([
                    Tab::make('English')->schema([
                        TextInput::make('name.en')->label(__('loyalty.fields.name'))->required()->maxLength(255),
                        Textarea::make('terms.en')->label(__('loyalty.fields.terms'))->rows(3)->maxLength(2000),
                    ]),
                    Tab::make('العربية')->schema([
                        TextInput::make('name.ar')->label(__('loyalty.fields.name'))->required()->maxLength(255)->extraInputAttributes(['dir' => 'rtl']),
                        Textarea::make('terms.ar')->label(__('loyalty.fields.terms'))->rows(3)->maxLength(2000)->extraInputAttributes(['dir' => 'rtl']),
                    ]),
                ])
                ->columnSpanFull(),

            Section::make(__('loyalty.sections.program'))
                ->schema([
                    Select::make('vendor_profile_id')
                        ->relationship('vendor', 'id')
                        ->getOptionLabelFromRecordUsing(fn ($record): string => $record->getTranslation('business_name', app()->getLocale(), useFallbackLocale: true))
                        ->searchable()
                        ->preload()
                        ->required()
                        ->label(__('loyalty.columns.vendor')),

                    Toggle::make('is_active')
                        ->label(__('loyalty.fields.is_active'))
                        ->default(true)
                        ->onColor('success')
                        ->offColor('danger'),

                    TextInput::make('points_per_currency_unit')
                        ->label(__('loyalty.fields.points_per_currency_unit'))
                        ->numeric()
                        ->step(0.0001)
                        ->required()
                        ->default(1)
                        ->helperText(__('loyalty.help.points_per_currency_unit')),

                    TextInput::make('points_value_minor')
                        ->label(__('loyalty.fields.points_value_minor'))
                        ->numeric()
                        ->required()
                        ->default(1)
                        ->prefix(fn (Get $get) => $get('points_value_currency') ?? 'EGP')
                        ->helperText(__('loyalty.help.points_value_minor')),

                    TextInput::make('points_value_currency')
                        ->label(__('loyalty.fields.points_value_currency'))
                        ->default('EGP')
                        ->maxLength(3)
                        ->required(),

                    TextInput::make('min_points_to_redeem')
                        ->label(__('loyalty.fields.min_points_to_redeem'))
                        ->numeric()
                        ->default(100)
                        ->required()
                        ->minValue(1),

                    TextInput::make('max_redeem_pct')
                        ->label(__('loyalty.fields.max_redeem_pct'))
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(100)
                        ->default(50)
                        ->required()
                        ->suffix('%'),

                    TextInput::make('points_expire_after_days')
                        ->label(__('loyalty.fields.points_expire_after_days'))
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(3650)
                        ->placeholder(__('loyalty.never_expires'))
                        ->nullable(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('loyalty.columns.public_id'))
                    ->copyable()
                    ->searchable()
                    ->limit(10),
                TextColumn::make('vendor.business_name')
                    ->label(__('loyalty.columns.vendor'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—'))
                    ->searchable(query: fn (Builder $query, string $search) => $query->whereHas(
                        'vendor',
                        fn ($q) => $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(business_name, '$.en')) LIKE ?", ["%{$search}%"])
                    )),
                TextColumn::make('name')
                    ->label(__('loyalty.columns.name'))
                    ->searchable()
                    ->formatStateUsing(fn ($state) => is_array($state)
                        ? ($state[app()->getLocale()] ?? $state['en'] ?? '')
                        : (string) $state),
                IconColumn::make('is_active')
                    ->label(__('loyalty.columns.is_active'))
                    ->boolean(),
                TextColumn::make('points_per_currency_unit')
                    ->label(__('loyalty.columns.points_per_currency_unit'))
                    ->numeric(decimalPlaces: 4),
                TextColumn::make('points_value_minor')
                    ->label(__('loyalty.columns.point_value'))
                    ->money('EGP', divideBy: 100),
                TextColumn::make('min_points_to_redeem')
                    ->label(__('loyalty.columns.min_points_to_redeem'))
                    ->numeric(),
                TextColumn::make('max_redeem_pct')
                    ->label(__('loyalty.columns.max_redeem_pct'))
                    ->suffix('%'),
                TextColumn::make('points_expire_after_days')
                    ->label(__('loyalty.columns.points_expire_after_days'))
                    ->default(__('loyalty.never_expires')),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label(__('loyalty.columns.is_active')),
                TrashedFilter::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoyaltyPrograms::route('/'),
            'create' => CreateLoyaltyProgram::route('/create'),
            'edit' => EditLoyaltyProgram::route('/{record}/edit'),
        ];
    }
}
