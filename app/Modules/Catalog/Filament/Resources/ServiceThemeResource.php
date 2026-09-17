<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources;

use App\Modules\Catalog\Application\Actions\DeleteServiceThemeAction;
use App\Modules\Catalog\Domain\Models\ServiceTheme;
use App\Modules\Catalog\Filament\Resources\ServiceThemeResource\Pages\CreateServiceTheme;
use App\Modules\Catalog\Filament\Resources\ServiceThemeResource\Pages\EditServiceTheme;
use App\Modules\Catalog\Filament\Resources\ServiceThemeResource\Pages\ListServiceThemes;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Concerns\Translatable;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ServiceThemeResource extends Resource
{
    use Translatable;

    protected static ?string $model = ServiceTheme::class;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.catalog');
    }

    protected static ?string $navigationIcon = 'heroicon-o-swatch';

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return __('catalog.nav.service_themes');
    }

    public static function getModelLabel(): string
    {
        return __('catalog.models.service_theme.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.models.service_theme.plural');
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Section::make(__('catalog.service_theme_details'))
                ->schema([
                    TextInput::make('code')
                        ->label(__('catalog.code'))
                        ->required()
                        ->maxLength(80)
                        ->unique(ignoreRecord: true),
                    TextInput::make('icon_path')
                        ->label(__('catalog.icon_path'))
                        ->maxLength(500),
                    Toggle::make('is_active')
                        ->label(__('catalog.is_active'))
                        ->default(true),
                ])
                ->columns(3),
            Tabs::make(__('catalog.name'))
                ->tabs([
                    Tab::make('English')->schema([
                        TextInput::make('name.en')
                            ->label(__('catalog.name_en'))
                            ->required()
                            ->maxLength(255),
                    ]),
                    Tab::make('Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©')->schema([
                        TextInput::make('name.ar')
                            ->label(__('catalog.name_ar'))
                            ->required()
                            ->maxLength(255)
                            ->extraInputAttributes(['dir' => 'rtl']),
                    ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label(__('catalog.code'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('name')
                    ->label(__('catalog.name'))
                    ->getStateUsing(fn (ServiceTheme $record): string => $record->getTranslation('name', app()->getLocale(), useFallbackLocale: true))
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label(__('catalog.is_active'))
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('code')
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->using(function (ServiceTheme $record): bool {
                        app(DeleteServiceThemeAction::class)->execute($record, auth()->user());

                        return true;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListServiceThemes::route('/'),
            'create' => CreateServiceTheme::route('/create'),
            'edit' => EditServiceTheme::route('/{record}/edit'),
        ];
    }
}
