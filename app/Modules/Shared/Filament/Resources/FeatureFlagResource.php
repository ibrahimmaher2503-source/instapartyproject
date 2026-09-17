<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources;

use App\Modules\Shared\Domain\Models\FeatureFlag;
use App\Modules\Shared\Filament\Resources\FeatureFlagResource\Pages\EditFeatureFlag;
use App\Modules\Shared\Filament\Resources\FeatureFlagResource\Pages\ListFeatureFlags;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FeatureFlagResource extends Resource
{
    protected static ?string $model = FeatureFlag::class;

    protected static ?string $navigationIcon = 'heroicon-o-flag';

    protected static ?int $navigationSort = 50;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settings');
    }

    public static function getModelLabel(): string
    {
        return __('shared.settings.feature_flag_singular');
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Section::make(__('shared.settings.flag_section'))->columns(2)->schema([
                TextInput::make('key')->disabled()->dehydrated(false)
                    ->helperText('Read-only. Flags prefixed with frontend.* are exposed publicly.'),
                Toggle::make('is_enabled'),
                TextInput::make('rollout_pct')->numeric()->minValue(0)->maxValue(100)->suffix('%'),
                Textarea::make('description')->rows(2)->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->badge()->color('info')->searchable()->sortable(),
                IconColumn::make('is_enabled')->boolean()->label(__('shared.settings.enabled')),
                TextColumn::make('rollout_pct')->suffix('%'),
                TextColumn::make('description')->limit(60)->placeholder('—'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('key')
            ->filters([
                TernaryFilter::make('is_enabled'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeatureFlags::route('/'),
            'edit' => EditFeatureFlag::route('/{record}/edit'),
        ];
    }
}
