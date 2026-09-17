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

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.trust_safety');
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
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
                                ->required()
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
                ->label('Badge Level'),

            Toggle::make('is_active')
                ->default(true)
                ->label('Active'),

            SpatieMediaLibraryFileUpload::make('trust-badge-icon')
                ->collection('trust-badge-icon')
                ->image()
                ->label('Badge Icon'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Name')
                    ->searchable(),

                TextColumn::make('level')
                    ->badge()
                    ->label('Level'),

                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('Created'),
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
