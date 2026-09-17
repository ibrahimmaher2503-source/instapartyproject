<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Filament\Resources;

use App\Modules\Promotions\Domain\Enums\PromoCodeScope;
use App\Modules\Promotions\Domain\Enums\PromoCodeType;
use App\Modules\Promotions\Domain\Models\PromoCode;
use App\Modules\Promotions\Filament\Resources\PromoCodeResource\Pages\CreatePromoCode;
use App\Modules\Promotions\Filament\Resources\PromoCodeResource\Pages\EditPromoCode;
use App\Modules\Promotions\Filament\Resources\PromoCodeResource\Pages\ListPromoCodes;
use Filament\Forms;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class PromoCodeResource extends Resource
{
    protected static ?string $model = PromoCode::class;

    protected static ?string $navigationIcon = 'heroicon-o-ticket';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.promotions');
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Section::make('Code Details')
                ->schema([
                    TextInput::make('code')
                        ->required()
                        ->unique(ignoreRecord: true)
                        ->maxLength(50)
                        ->label('Code'),

                    Select::make('type')
                        ->required()
                        ->options(PromoCodeType::class)
                        ->live()
                        ->label('Discount Type'),

                    TextInput::make('discount_percent')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->label('Discount Percent (%)')
                        ->visible(fn (Forms\Components\Utilities\Get $get) => $get('type') === PromoCodeType::Percentage->value),

                    TextInput::make('discount_minor')
                        ->numeric()
                        ->minValue(1)
                        ->label('Discount Amount (piastres)')
                        ->visible(fn (Forms\Components\Utilities\Get $get) => $get('type') === PromoCodeType::Fixed->value),

                    TextInput::make('max_discount_minor')
                        ->numeric()
                        ->label('Max Discount Cap (piastres)')
                        ->helperText('Leave empty for uncapped percentage discounts.')
                        ->visible(fn (Forms\Components\Utilities\Get $get) => $get('type') === PromoCodeType::Percentage->value),
                ])
                ->columns(2),

            Section::make('Restrictions')
                ->schema([
                    Select::make('scope')
                        ->required()
                        ->options(PromoCodeScope::class)
                        ->default(PromoCodeScope::Platform->value)
                        ->live()
                        ->label('Scope'),

                    TextInput::make('scope_id')
                        ->numeric()
                        ->label('Scope Entity ID')
                        ->helperText('Vendor / Category / Service ID for non-platform codes.')
                        ->visible(fn (Forms\Components\Utilities\Get $get) => $get('scope') !== PromoCodeScope::Platform->value),

                    TextInput::make('min_order_minor')
                        ->numeric()
                        ->label('Min Order (piastres)')
                        ->helperText('Leave empty for no minimum.'),

                    TextInput::make('max_uses')
                        ->numeric()
                        ->label('Max Uses')
                        ->helperText('Leave empty for unlimited.'),
                ])
                ->columns(2),

            Section::make('Validity')
                ->schema([
                    DateTimePicker::make('starts_at')
                        ->label('Starts At')
                        ->native(false),

                    DateTimePicker::make('expires_at')
                        ->label('Expires At')
                        ->native(false),

                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->copyable()
                    ->label('Code'),

                TextColumn::make('type')
                    ->badge()
                    ->color(fn (PromoCodeType $state): string => match ($state) {
                        PromoCodeType::Percentage => 'warning',
                        PromoCodeType::Fixed => 'success',
                    })
                    ->label('Type'),

                TextColumn::make('scope')
                    ->badge()
                    ->label('Scope'),

                TextColumn::make('used_count')
                    ->label('Uses')
                    ->formatStateUsing(fn (PromoCode $record): string => $record->used_count.'/'.(string) ($record->max_uses ?? '∞')),

                TextColumn::make('expires_at')
                    ->dateTime()
                    ->label('Expires'),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->options(PromoCodeType::class)
                    ->label('Type'),

                SelectFilter::make('scope')
                    ->options(PromoCodeScope::class)
                    ->label('Scope'),

                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->actions([
                EditAction::make(),
                Action::make('deactivate')
                    ->label('Deactivate')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (PromoCode $record): bool => $record->is_active)
                    ->action(fn (PromoCode $record) => $record->update(['is_active' => false])),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromoCodes::route('/'),
            'create' => CreatePromoCode::route('/create'),
            'edit' => EditPromoCode::route('/{record}/edit'),
        ];
    }
}
