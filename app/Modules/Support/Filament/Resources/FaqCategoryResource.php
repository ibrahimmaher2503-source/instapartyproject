<?php

declare(strict_types=1);

namespace App\Modules\Support\Filament\Resources;

use App\Modules\Support\Domain\Models\FaqCategory;
use App\Modules\Support\Filament\Resources\FaqCategoryResource\Pages\CreateFaqCategory;
use App\Modules\Support\Filament\Resources\FaqCategoryResource\Pages\EditFaqCategory;
use App\Modules\Support\Filament\Resources\FaqCategoryResource\Pages\ListFaqCategories;
use Filament\Forms\Components\Section;
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

class FaqCategoryResource extends Resource
{
    use Translatable;

    protected static ?string $model = FaqCategory::class;

    protected static ?string $navigationIcon = 'heroicon-o-folder';

    protected static ?int $navigationSort = 110;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.support');
    }

    public static function getNavigationLabel(): string
    {
        return __('support::support.faq_categories.plural');
    }

    public static function getModelLabel(): string
    {
        return __('support::support.faq_categories.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('support::support.faq_categories.plural');
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Section::make(__('support::support.faq_categories.section'))->schema([
                TextInput::make('name')
                    ->label(__('support::support.faq_categories.name'))
                    ->required()
                    ->maxLength(255),

                TextInput::make('slug')
                    ->label(__('support::support.faq_categories.slug'))
                    ->required()
                    ->maxLength(100)
                    ->unique(ignoreRecord: true),

                TextInput::make('sort_order')
                    ->label(__('support::support.faq_categories.sort_order'))
                    ->numeric()
                    ->default(0)
                    ->required(),

                Toggle::make('is_active')
                    ->label(__('support::support.faq_categories.is_active'))
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label(__('support::support.faq_categories.name'))
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label(__('support::support.faq_categories.slug'))
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label(__('support::support.faq_categories.sort_order'))
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label(__('support::support.faq_categories.is_active'))
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaqCategories::route('/'),
            'create' => CreateFaqCategory::route('/create'),
            'edit' => EditFaqCategory::route('/{record}/edit'),
        ];
    }
}
