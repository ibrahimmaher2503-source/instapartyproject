<?php

declare(strict_types=1);

namespace App\Modules\TrustSafety\Filament\Resources;

use App\Modules\TrustSafety\Domain\Models\TrustBadge;
use App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource\Pages\CreateTrustBadge;
use App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource\Pages\EditTrustBadge;
use App\Modules\TrustSafety\Filament\Resources\TrustBadgeResource\Pages\ListTrustBadges;
use Filament\Forms\Components\SpatieMediaLibraryFileUpload;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
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

class TrustBadgeResource extends Resource
{
    use Translatable;

    protected static ?string $model = TrustBadge::class;

    protected static ?string $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 100;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.trust_safety');
    }

    public static function getNavigationLabel(): string
    {
        return __('trust-safety::trust-safety.badges.plural');
    }

    public static function getModelLabel(): string
    {
        return __('trust-safety::trust-safety.badges.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('trust-safety::trust-safety.badges.plural');
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Tabs::make(__('trust-safety::trust-safety.badges.translations'))
                ->tabs([
                    Tab::make(__('trust-safety::trust-safety.badges.tab_english'))
                        ->schema([
                            TextInput::make('name.en')
                                ->label(__('trust-safety::trust-safety.badges.name_en'))
                                ->required()
                                ->maxLength(255),
                            Textarea::make('description.en')
                                ->label(__('trust-safety::trust-safety.badges.description_en'))
                                ->required()
                                ->rows(3),
                        ]),
                    Tab::make(__('trust-safety::trust-safety.badges.tab_arabic'))
                        ->schema([
                            TextInput::make('name.ar')
                                ->label(__('trust-safety::trust-safety.badges.name_ar'))
                                ->required()
                                ->maxLength(255),
                            Textarea::make('description.ar')
                                ->label(__('trust-safety::trust-safety.badges.description_ar'))
                                ->required()
                                ->rows(3),
                        ]),
                ])
                ->columnSpanFull(),

            TextInput::make('level')
                ->numeric()
                ->minValue(1)
                ->maxValue(5)
                ->default(1)
                ->label(__('trust-safety::trust-safety.badges.level')),

            Toggle::make('is_active')
                ->default(true)
                ->label(__('trust-safety::trust-safety.badges.active')),

            SpatieMediaLibraryFileUpload::make('trust-badge-icon')
                ->collection('trust-badge-icon')
                ->image()
                ->label(__('trust-safety::trust-safety.badges.icon')),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('trust-safety::trust-safety.badges.name'))
                    ->searchable(),

                TextColumn::make('level')
                    ->badge()
                    ->label(__('trust-safety::trust-safety.badges.level')),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label(__('trust-safety::trust-safety.badges.active')),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label(__('trust-safety::trust-safety.badges.created_at')),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('level');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTrustBadges::route('/'),
            'create' => CreateTrustBadge::route('/create'),
            'edit' => EditTrustBadge::route('/{record}/edit'),
        ];
    }
}
