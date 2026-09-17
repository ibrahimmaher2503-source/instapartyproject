<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources;

use App\Modules\Shared\Application\Actions\PublishCmsPageAction;
use App\Modules\Shared\Application\Actions\UnpublishCmsPageAction;
use App\Modules\Shared\Domain\Models\CmsPage;
use App\Modules\Shared\Filament\Resources\CmsPageResource\Pages\EditCmsPage;
use App\Modules\Shared\Filament\Resources\CmsPageResource\Pages\ListCmsPages;
use Filament\Forms\Components\Builder;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CmsPageResource extends Resource
{
    protected static ?string $model = CmsPage::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-text';

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.settings');
    }

    public static function getNavigationLabel(): string
    {
        return __('shared::shared.cms.nav_label');
    }

    public static function getModelLabel(): string
    {
        return __('shared::shared.cms.model_singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('shared::shared.cms.model_plural');
    }

    protected static ?int $navigationSort = 20;

    public static function form(Form $schema): Form
    {
        return $schema->components([
            Tabs::make('Translations')
                ->tabs([
                    Tab::make('English')
                        ->schema([
                            TextInput::make('title.en')
                                ->label('Title (English)')
                                ->required()
                                ->maxLength(255),
                            RichEditor::make('body.en')
                                ->label('Body (English)')
                                ->required()
                                ->columnSpanFull(),
                            TextInput::make('meta_description.en')
                                ->label('Meta Description (English)')
                                ->maxLength(255),
                        ]),
                    Tab::make('Ø§Ù„Ø¹Ø±Ø¨ÙŠØ©')
                        ->schema([
                            TextInput::make('title.ar')
                                ->label('Ø§Ù„Ø¹Ù†ÙˆØ§Ù† (Ø¹Ø±Ø¨ÙŠ)')
                                ->required()
                                ->maxLength(255),
                            RichEditor::make('body.ar')
                                ->label('Ø§Ù„Ù…Ø­ØªÙˆÙ‰ (Ø¹Ø±Ø¨ÙŠ)')
                                ->required()
                                ->columnSpanFull(),
                            TextInput::make('meta_description.ar')
                                ->label('ÙˆØµÙ Ø§Ù„Ù…ÙŠØªØ§ (Ø¹Ø±Ø¨ÙŠ)')
                                ->maxLength(255),
                        ]),
                ])
                ->columnSpanFull(),

            Section::make(__('shared::shared.cms.page_info'))
                ->schema([
                    TextInput::make('slug')
                        ->label(__('shared::shared.cms.slug'))
                        ->disabled()
                        ->dehydrated(false),
                ])
                ->columns(2),

            Section::make(__('shared::shared.cms.structured_blocks'))
                ->description('Use the body editor above for prose. Add structured blocks here for FAQs, info grids, etc.')
                ->collapsed()
                ->schema([
                    Builder::make('blocks')
                        ->blocks([
                            Block::make('faq')
                                ->label('FAQ accordion')
                                ->schema([
                                    Repeater::make('items')->schema([
                                        TextInput::make('question.en')->required()->maxLength(240),
                                        TextInput::make('question.ar')->required()->maxLength(240),
                                        Textarea::make('answer.en')->required()->maxLength(2000),
                                        Textarea::make('answer.ar')->required()->maxLength(2000),
                                    ])->columns(2)->minItems(1)->collapsible(),
                                ]),
                            Block::make('info_grid')
                                ->label('Info grid')
                                ->schema([
                                    Repeater::make('items')->schema([
                                        TextInput::make('icon')->maxLength(64),
                                        TextInput::make('heading.en')->required()->maxLength(120),
                                        TextInput::make('heading.ar')->required()->maxLength(120),
                                        Textarea::make('body.en')->maxLength(600),
                                        Textarea::make('body.ar')->maxLength(600),
                                    ])->columns(2)->minItems(1)->collapsible(),
                                ]),
                            Block::make('contact_embed')
                                ->label('Contact form / map embed')
                                ->schema([
                                    TextInput::make('iframe_url')->url()->required()->maxLength(1024),
                                    TextInput::make('height_px')->numeric()->default(420),
                                ]),
                        ])
                        ->collapsible()
                        ->reorderable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('slug')
                    ->badge()
                    ->color('info')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('title')
                    ->label('Title')
                    ->formatStateUsing(fn (CmsPage $record): string => $record->getTranslation('title', app()->getLocale()) ?: 'â€”')
                    ->limit(50),
                IconColumn::make('is_published')
                    ->boolean()
                    ->label(__('shared::shared.cms.published')),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('â€”'),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                TernaryFilter::make('is_published')
                    ->label(__('shared::shared.cms.published')),
            ])
            ->actions([
                Action::make('publish')
                    ->label(__('shared::shared.cms.publish'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (CmsPage $record): bool => ! $record->is_published)
                    ->requiresConfirmation()
                    ->action(function (CmsPage $record): void {
                        app(PublishCmsPageAction::class)->execute($record);
                        Notification::make()
                            ->title(__('shared::shared.cms.published_successfully'))
                            ->success()
                            ->send();
                    }),
                Action::make('unpublish')
                    ->label(__('shared::shared.cms.unpublish'))
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->visible(fn (CmsPage $record): bool => $record->is_published)
                    ->requiresConfirmation()
                    ->action(function (CmsPage $record): void {
                        app(UnpublishCmsPageAction::class)->execute($record);
                        Notification::make()
                            ->title(__('shared::shared.cms.unpublished_msg'))
                            ->warning()
                            ->send();
                    }),
                EditAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCmsPages::route('/'),
            'edit' => EditCmsPage::route('/{record}/edit'),
        ];
    }
}
