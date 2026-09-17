<?php

declare(strict_types=1);

namespace App\Modules\Support\Filament\Resources;

use App\Modules\Support\Domain\Models\FaqItem;
use App\Modules\Support\Filament\Resources\FaqItemResource\Pages\CreateFaqItem;
use App\Modules\Support\Filament\Resources\FaqItemResource\Pages\EditFaqItem;
use App\Modules\Support\Filament\Resources\FaqItemResource\Pages\ListFaqItems;
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
use Filament\Tables\Table;

class FaqItemResource extends Resource
{
    use Translatable;

    protected static ?string $model = FaqItem::class;

    protected static ?string $navigationIcon = 'heroicon-o-question-mark-circle';

    protected static ?int $navigationSort = 2;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.support');
    }

    public static function getTranslatableLocales(): array
    {
        return ['en', 'ar'];
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Section::make()->schema([
                Select::make('faq_category_id')
                    ->relationship('category', 'name->en')
                    ->required()
                    ->searchable()
                    ->preload(),

                TextInput::make('question')
                    ->required()
                    ->maxLength(500),

                RichEditor::make('answer')
                    ->required()
                    ->columnSpanFull(),

                TextInput::make('sort_order')
                    ->numeric()
                    ->default(0)
                    ->required(),

                Toggle::make('is_active')
                    ->default(true),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('question')
                    ->limit(60)
                    ->searchable(),

                TextColumn::make('category.name')
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->filters([
                SelectFilter::make('faq_category_id')
                    ->relationship('category', 'name->en')
                    ->label('Category'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFaqItems::route('/'),
            'create' => CreateFaqItem::route('/create'),
            'edit' => EditFaqItem::route('/{record}/edit'),
        ];
    }
}
