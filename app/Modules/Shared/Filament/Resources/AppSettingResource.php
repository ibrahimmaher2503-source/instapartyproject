<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources;

use App\Modules\Shared\Domain\Models\AppSetting;
use App\Modules\Shared\Filament\Resources\AppSettingResource\Pages\EditAppSetting;
use App\Modules\Shared\Filament\Resources\AppSettingResource\Pages\ListAppSettings;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Utilities\Get;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class AppSettingResource extends Resource
{
    protected static ?string $model = AppSetting::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static ?int $navigationSort = 40;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settings');
    }

    public static function getModelLabel(): string
    {
        return __('shared.settings.app_setting_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('shared.settings.app_setting_plural');
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Section::make(__('shared.settings.setting_section'))->columns(2)->schema([
                TextInput::make('key')
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('Internal identifier — read-only to prevent breaking consumers.'),
                Textarea::make('description')->rows(2)->columnSpanFull(),

                Hidden::make('_is_scalar'),

                TextInput::make('value_text')
                    ->label(__('shared.settings.value'))
                    ->visible(fn (Get $get): bool => (bool) $get('_is_scalar'))
                    ->required(fn (Get $get): bool => (bool) $get('_is_scalar'))
                    ->dehydrated(false)
                    ->columnSpanFull(),

                KeyValue::make('value')
                    ->keyLabel(__('shared.settings.property'))
                    ->valueLabel(__('shared.settings.value'))
                    ->reorderable(false)
                    ->visible(fn (Get $get): bool => ! (bool) $get('_is_scalar'))
                    ->nullable()
                    ->columnSpanFull(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->badge()->color('info')->searchable()->sortable(),
                TextColumn::make('description')->limit(60)->placeholder('—'),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->defaultSort('key')
            ->actions([
                EditAction::make()->after(function ($record): void {
                    $record->updated_by = Auth::id();
                    $record->saveQuietly();
                }),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAppSettings::route('/'),
            'edit' => EditAppSetting::route('/{record}/edit'),
        ];
    }
}
