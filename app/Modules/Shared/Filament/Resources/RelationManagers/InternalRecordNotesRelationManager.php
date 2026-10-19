<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Resources\RelationManagers;

use App\Modules\Shared\Application\Actions\AddInternalRecordNoteAction;
use App\Modules\Shared\Application\Actions\SyncInternalRecordTagsAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class InternalRecordNotesRelationManager extends RelationManager
{
    protected static string $relationship = 'internalNotes';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('shared::internal-records.title');
    }

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return filament()->getCurrentPanel()?->getId() === 'admin' && Gate::allows('view', $ownerRecord);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kind')->label(__('shared::internal-records.kind'))
                    ->formatStateUsing(fn (string $state): string => __('shared::internal-records.'.$state)),
                TextColumn::make('body')->label(__('shared::internal-records.body'))->wrap(),
                TextColumn::make('author.name')->label(__('shared::internal-records.author'))
                    ->default(__('shared::internal-records.former_author')),
                TextColumn::make('created_at')->label(__('shared::internal-records.created_at'))->dateTime()->sortable(),
            ])
            ->headerActions([
                Action::make('addInternalNote')
                    ->label(__('shared::internal-records.add_note'))
                    ->visible(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->form([
                        Select::make('kind')->label(__('shared::internal-records.kind'))
                            ->options([
                                'note' => __('shared::internal-records.note'),
                                'contact' => __('shared::internal-records.contact'),
                            ])->required(),
                        Textarea::make('body')->label(__('shared::internal-records.body'))
                            ->required()->minLength(3)->maxLength(4000)->rows(4),
                    ])
                    ->action(function (array $data): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof Authenticatable, 403);
                        app(AddInternalRecordNoteAction::class)->execute(
                            $this->getOwnerRecord(), $actor, $data['kind'], $data['body'],
                        );
                        Notification::make()->title(__('shared::internal-records.note_added'))->success()->send();
                    }),
                Action::make('manageInternalTags')
                    ->label(__('shared::internal-records.manage_tags'))
                    ->visible(fn (): bool => Gate::allows('update', $this->getOwnerRecord()))
                    ->fillForm(fn (): array => [
                        'tags' => $this->getOwnerRecord()->tagsWithType('internal_record')
                            ->map(fn ($tag): array => [
                                'en' => $tag->getTranslation('name', 'en'),
                                'ar' => $tag->getTranslation('name', 'ar'),
                            ])->values()->all(),
                    ])
                    ->form([
                        Repeater::make('tags')->label(__('shared::internal-records.tags'))
                            ->schema([
                                TextInput::make('en')->label(__('shared::internal-records.tag_en'))->required()->maxLength(80),
                                TextInput::make('ar')->label(__('shared::internal-records.tag_ar'))->required()->maxLength(80)->extraInputAttributes(['dir' => 'rtl']),
                            ])->defaultItems(0)->maxItems(20)->columns(2),
                    ])
                    ->action(function (array $data): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof Authenticatable, 403);
                        app(SyncInternalRecordTagsAction::class)->execute(
                            $this->getOwnerRecord(), $actor, $data['tags'] ?? [],
                        );
                        Notification::make()->title(__('shared::internal-records.tags_updated'))->success()->send();
                    }),
            ])
            ->actions([])
            ->defaultSort('created_at', 'desc')
            ->emptyStateHeading(__('shared::internal-records.empty'));
    }
}
