<?php

declare(strict_types=1);

namespace App\Modules\Support\Filament\Resources;

use App\Modules\Support\Application\Actions\TransitionSupportTicketStatusAction;
use App\Modules\Support\Domain\Enums\SupportTicketStatus;
use App\Modules\Support\Domain\Models\SupportTicket;
use App\Modules\Support\Filament\Resources\SupportTicketResource\Pages\ListSupportTickets;
use App\Modules\Support\Filament\Resources\SupportTicketResource\Pages\ViewSupportTicket;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 3;

    public static function getNavigationGroup(): ?string
    {
        return __('admin.nav.groups.support');
    }

    public static function getNavigationLabel(): string
    {
        return __('support::support.resource.plural');
    }

    public static function getModelLabel(): string
    {
        return __('support::support.resource.singular');
    }

    public static function getPluralModelLabel(): string
    {
        return __('support::support.resource.plural');
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $schema): Form
    {
        return $schema->components([]);
    }

    public static function infolist(Infolist $schema): Infolist
    {
        return $schema->components([
            Section::make(__('support::support.resource.details'))->schema([
                TextEntry::make('public_id')->label(__('support::support.columns.reference'))->copyable(),
                TextEntry::make('status')
                    ->label(__('support::support.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (SupportTicketStatus $state): string => $state->label())
                    ->color(fn (SupportTicketStatus $state): string => self::statusColor($state)),
                TextEntry::make('user.name')->label(__('support::support.columns.requester'))->default(__('support::support.guest')),
                TextEntry::make('assignee.name')->label(__('support::support.columns.assignee'))->default(__('support::support.unassigned')),
                TextEntry::make('email')->label(__('support::support.columns.email'))->placeholder('—'),
                TextEntry::make('subject')->label(__('support::support.columns.subject')),
                TextEntry::make('body')->label(__('support::support.columns.message'))->columnSpanFull(),
                TextEntry::make('created_at')->label(__('support::support.columns.created_at'))->dateTime(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('support::support.columns.reference'))
                    ->searchable()
                    ->copyable()
                    ->limit(14)
                    ->tooltip(fn (SupportTicket $record): string => $record->public_id),
                TextColumn::make('subject')
                    ->label(__('support::support.columns.subject'))
                    ->limit(50)
                    ->searchable(),
                TextColumn::make('requester')
                    ->label(__('support::support.columns.requester'))
                    ->state(fn (SupportTicket $record): string => $record->user?->name ?? $record->email ?? __('support::support.guest')),
                TextColumn::make('status')
                    ->label(__('support::support.columns.status'))
                    ->badge()
                    ->formatStateUsing(fn (SupportTicketStatus $state): string => $state->label())
                    ->color(fn (SupportTicketStatus $state): string => self::statusColor($state)),
                TextColumn::make('assignee.name')
                    ->label(__('support::support.columns.assignee'))
                    ->default(__('support::support.unassigned'))
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->label(__('support::support.columns.updated_at'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(SupportTicketStatus::class),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('markInProgress')
                    ->label(__('support::support.actions.mark_in_progress'))
                    ->icon('heroicon-o-arrow-right')
                    ->color('info')
                    ->visible(fn (SupportTicket $r): bool => $r->status === SupportTicketStatus::Open
                        && auth()->user()?->can('update_support::ticket') === true)
                    ->action(function (SupportTicket $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof Authenticatable, 403);
                        app(TransitionSupportTicketStatusAction::class)->execute(
                            $record,
                            $actor,
                            SupportTicketStatus::InProgress,
                        );
                        Notification::make()->title(__('support::support.notifications.in_progress'))->info()->send();
                    }),
                Action::make('resolve')
                    ->label(__('support::support.actions.resolve'))
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (SupportTicket $r): bool => $r->status === SupportTicketStatus::InProgress
                        && auth()->user()?->can('update_support::ticket') === true)
                    ->requiresConfirmation()
                    ->action(function (SupportTicket $record): void {
                        $actor = auth()->user();

                        abort_unless($actor instanceof Authenticatable, 403);
                        app(TransitionSupportTicketStatusAction::class)->execute(
                            $record,
                            $actor,
                            SupportTicketStatus::Resolved,
                        );
                        Notification::make()->title(__('support::support.notifications.resolved'))->success()->send();
                    }),
            ])
            ->emptyStateIcon('heroicon-o-chat-bubble-left-right')
            ->emptyStateHeading(__('support::support.empty.heading'))
            ->emptyStateDescription(__('support::support.empty.description'));
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSupportTickets::route('/'),
            'view' => ViewSupportTicket::route('/{record}'),
        ];
    }

    private static function statusColor(SupportTicketStatus $status): string
    {
        return match ($status) {
            SupportTicketStatus::Open => 'warning',
            SupportTicketStatus::InProgress => 'info',
            SupportTicketStatus::Resolved => 'success',
            SupportTicketStatus::Closed => 'gray',
        };
    }
}
