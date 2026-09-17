<?php

declare(strict_types=1);

namespace App\Modules\Loyalty\Filament\Resources;

use App\Modules\Loyalty\Domain\Enums\RuleKind;
use App\Modules\Loyalty\Domain\Models\LoyaltyProgram;
use App\Modules\Loyalty\Domain\Models\LoyaltyRule;
use App\Modules\Loyalty\Filament\Resources\LoyaltyRuleResource\Pages\ListLoyaltyRules;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class LoyaltyRuleResource extends Resource
{
    use Translatable;

    protected static ?string $model = LoyaltyRule::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-vertical';

    protected static ?int $navigationSort = 20;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.loyalty');
    }

    public static function getNavigationLabel(): string
    {
        return __('loyalty.nav.rules');
    }

    public static function getModelLabel(): string
    {
        return __('loyalty.models.rule.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('loyalty.models.rule.plural');
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Section::make(__('loyalty.sections.rule'))
                ->schema([
                    Select::make('loyalty_program_id')
                        ->label(__('loyalty.fields.program'))
                        ->relationship('program', 'id')
                        ->getOptionLabelFromRecordUsing(function (LoyaltyProgram $record): string {
                            $name = $record->getTranslation('name', app()->getLocale(), useFallbackLocale: true);

                            return sprintf('#%d — %s', $record->vendor_profile_id, $name ?: $record->public_id);
                        })
                        ->searchable()
                        ->preload()
                        ->required(),

                    TextInput::make('label')
                        ->label(__('loyalty.fields.label'))
                        ->maxLength(255),

                    Select::make('rule_kind')
                        ->label(__('loyalty.fields.rule_kind'))
                        ->options(collect(RuleKind::cases())
                            ->mapWithKeys(fn (RuleKind $k) => [$k->value => $k->label()])
                            ->all())
                        ->required(),

                    TextInput::make('multiplier')
                        ->label(__('loyalty.fields.multiplier'))
                        ->numeric()
                        ->step(0.01)
                        ->default(1.00)
                        ->required()
                        ->minValue(0.01)
                        ->maxValue(99.99),

                    Toggle::make('is_active')
                        ->label(__('loyalty.fields.is_active'))
                        ->default(true),

                    DateTimePicker::make('starts_at')
                        ->label(__('loyalty.fields.starts_at'))
                        ->nullable(),

                    DateTimePicker::make('ends_at')
                        ->label(__('loyalty.fields.ends_at'))
                        ->nullable()
                        ->afterOrEqual('starts_at'),

                    KeyValue::make('conditions')
                        ->label(__('loyalty.fields.conditions'))
                        ->keyLabel(__('loyalty.condition_key'))
                        ->valueLabel(__('loyalty.condition_value'))
                        ->nullable()
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['program']);
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
                TextColumn::make('program.name')
                    ->label(__('loyalty.columns.program'))
                    ->formatStateUsing(fn ($state): string => is_array($state) ? ($state[app()->getLocale()] ?? $state['en'] ?? '—') : ($state ?? '—')),
                TextColumn::make('label')
                    ->label(__('loyalty.columns.label'))
                    ->searchable()
                    ->formatStateUsing(fn ($state) => is_array($state)
                        ? ($state[app()->getLocale()] ?? $state['en'] ?? '')
                        : (string) ($state ?? '')),
                TextColumn::make('rule_kind')
                    ->label(__('loyalty.columns.rule_kind'))
                    ->badge()
                    ->color(fn (RuleKind $state): string => match ($state) {
                        RuleKind::FirstBooking => 'success',
                        RuleKind::CategoryBonus => 'warning',
                        RuleKind::ThresholdBonus => 'info',
                        RuleKind::Referral => 'primary',
                    })
                    ->formatStateUsing(fn (RuleKind $state) => $state->label()),
                TextColumn::make('multiplier')
                    ->label(__('loyalty.columns.multiplier'))
                    ->numeric(decimalPlaces: 2)
                    ->suffix('×'),
                IconColumn::make('is_active')
                    ->label(__('loyalty.columns.is_active'))
                    ->boolean(),
                TextColumn::make('starts_at')
                    ->label(__('loyalty.columns.starts_at'))
                    ->dateTime()
                    ->placeholder(__('loyalty.no_start')),
                TextColumn::make('ends_at')
                    ->label(__('loyalty.columns.ends_at'))
                    ->dateTime()
                    ->placeholder(__('loyalty.no_end')),
                TextColumn::make('created_at')
                    ->label(__('admin.common.created_at'))
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('rule_kind')
                    ->label(__('loyalty.columns.rule_kind'))
                    ->options(collect(RuleKind::cases())
                        ->mapWithKeys(fn (RuleKind $k) => [$k->value => $k->label()])
                        ->all()),
                TernaryFilter::make('is_active')
                    ->label(__('loyalty.columns.is_active')),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoyaltyRules::route('/'),
        ];
    }
}
