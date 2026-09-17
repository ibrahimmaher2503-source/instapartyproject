<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources;

use App\Modules\Catalog\Application\Actions\DeleteCategoryAction;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Filament\Resources\CategoryResource\Pages\CreateCategory;
use App\Modules\Catalog\Filament\Resources\CategoryResource\Pages\EditCategory;
use App\Modules\Catalog\Filament\Resources\CategoryResource\Pages\ListCategories;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoryResource extends Resource
{
    protected static ?string $model = Category::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.catalog');
    }

    protected static ?int $navigationSort = 20;

    public static function getNavigationLabel(): string
    {
        return __('catalog.nav.categories');
    }

    public static function getModelLabel(): string
    {
        return __('catalog.models.category.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('catalog.models.category.plural');
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Section::make(__('catalog.category_details'))
                ->schema([
                    Select::make('parent_id')
                        ->label(__('catalog.parent_category'))
                        ->options(fn () => Category::active()->get()->mapWithKeys(fn (Category $category): array => [
                            $category->id => $category->getTranslation('name', app()->getLocale(), useFallbackLocale: true),
                        ]))
                        ->searchable()
                        ->nullable()
                        ->placeholder(__('catalog.no_parent')),

                    TextInput::make('code')
                        ->required()
                        ->maxLength(80)
                        ->unique(ignoreRecord: true)
                        ->label(__('catalog.code')),

                    TextInput::make('sort_order')
                        ->numeric()
                        ->default(0)
                        ->label(__('catalog.sort_order')),

                    Toggle::make('is_active')
                        ->default(true)
                        ->label(__('catalog.is_active')),

                    FileUpload::make('icon_path')
                        ->label(__('catalog.icon'))
                        ->disk('public')
                        ->directory('icons/categories')
                        ->image()
                        ->maxSize(512)
                        ->nullable()
                        ->columnSpanFull(),
                ])
                ->columns(2),

            Tabs::make('Translations')
                ->tabs([
                    Tab::make('English')
                        ->schema([
                            TextInput::make('name.en')
                                ->required()
                                ->maxLength(255)
                                ->label(__('catalog.fields.name_en')),
                            TextInput::make('description.en')
                                ->maxLength(500)
                                ->label(__('catalog.fields.description_en')),
                        ]),
                    Tab::make('العربية')
                        ->schema([
                            TextInput::make('name.ar')
                                ->required()
                                ->maxLength(255)
                                ->label(__('catalog.fields.name_ar'))
                                ->extraInputAttributes([
                                    'dir' => 'rtl',
                                    'style' => 'text-align: right;',
                                ]),

                            TextInput::make('description.ar')
                                ->maxLength(500)
                                ->label(__('catalog.fields.description_ar'))
                                ->extraInputAttributes([
                                    'dir' => 'rtl',
                                    'style' => 'text-align: right;',
                                ]),
                        ]),
                ])
                ->columnSpanFull(),

            Section::make(__('catalog.allowed_product_types'))
                ->schema([
                    CheckboxList::make('allowed_product_types')
                        ->options([
                            ProductType::Rental->value => ProductType::Rental->label(),
                            ProductType::Sale->value => ProductType::Sale->label(),
                            ProductType::Digital->value => ProductType::Digital->label(),
                        ])
                        ->columns(3)
                        ->required()
                        ->label(__('catalog.allowed_product_types')),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->searchable()
                    ->sortable()
                    ->label(__('catalog.code')),

                TextColumn::make('name')
                    ->getStateUsing(fn (Category $record): string => $record->getTranslation('name', app()->getLocale(), useFallbackLocale: true))
                    ->searchable(query: fn ($query, $search) => $query
                        ->where('name->en', 'like', "%{$search}%")
                        ->orWhere('name->ar', 'like', "%{$search}%"))
                    ->label(__('catalog.name')),

                TextColumn::make('parent.code')
                    ->label(__('catalog.parent_category'))
                    ->placeholder('-'),

                TextColumn::make('allowed_product_types')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => collect((array) $state)
                        ->map(fn (string $type): string => ProductType::tryFrom($type)?->label() ?? $type)
                        ->implode(', '))
                    ->label(__('catalog.allowed_product_types')),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->using(function (Category $record): bool {
                        app(DeleteCategoryAction::class)->execute($record, auth()->user());

                        return true;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCategories::route('/'),
            'create' => CreateCategory::route('/create'),
            'edit' => EditCategory::route('/{record}/edit'),
        ];
    }
}
