<?php

declare(strict_types=1);

namespace App\Modules\Support\Filament\Resources;

use App\Modules\Shared\Application\Services\AdminQueueSlaSettings;
use App\Modules\Shared\Domain\Enums\AdminQueue;
use App\Modules\Shared\Filament\Resources\RelationManagers\InternalRecordNotesRelationManager;
use App\Modules\Shared\Filament\Tables\OperationalDateFilter;
use App\Modules\Shared\Filament\Tables\OperationalExport;
use App\Modules\Shared\Filament\Tables\ReferenceSearch;
use App\Modules\Shared\Filament\Tables\ScopedBulkAction;
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
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class SupportTicketResource extends Resource
{
    protected static ?string $model = SupportTicket::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?int $navigationSort = 20;

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
            ->headerActions([OperationalExport::make(static::class, ['public_id', 'status', 'created_at'])])
            ->columns([
                TextColumn::make('public_id')
                    ->label(__('support::support.columns.reference'))
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->where(function (Builder $match) use ($search): void {
                        ReferenceSearch::apply($match, $search, [
                            '' => ['public_id', 'email', 'subject'],
                            'user' => ['public_id', 'name', 'email', 'phone_e164'],
                        ]);
                        $match->orWhereIn('booking_id', fn ($bookings) => $bookings->select('id')->from('bookings')->where(function ($references) use ($search): void {
                            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $search).'%';
                            $references->whereRaw("public_id LIKE ? ESCAPE '!'", [$pattern])->orWhereRaw("reference_no LIKE ? ESCAPE '!'", [$pattern]);
                        }));
                    }))
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
                TextColumn::make('queue_age')
                    ->label(__('shared::shared.admin_queue_sla.queue_age'))
                    ->getStateUsing(fn (SupportTicket $record): ?string => self::isInSupportQueue($record)
                        ? Carbon::parse($record->created_at, 'UTC')->diffForHumans(now('UTC'))
                        : null)
                    ->placeholder(__('shared::shared.admin_queue_sla.not_active')),
                TextColumn::make('sla_deadline')
                    ->label(__('shared::shared.admin_queue_sla.deadline'))
                    ->dateTime('d M Y H:i')
                    ->timezone(fn () => auth()->user()?->timezone ?? 'Africa/Cairo')
                    ->getStateUsing(fn (SupportTicket $record) => self::isInSupportQueue($record)
                        ? app(AdminQueueSlaSettings::class)->deadlineFor(AdminQueue::Support, $record->created_at)
                        : null)
                    ->suffix(fn (SupportTicket $record): ?string => self::isInSupportQueue($record)
                        ? ' '.(auth()->user()?->timezone ?? 'Africa/Cairo')
                        : null)
                    ->placeholder(__('shared::shared.admin_queue_sla.not_active')),
                TextColumn::make('sla_status')
                    ->label(__('shared::shared.admin_queue_sla.status'))
                    ->badge()
                    ->getStateUsing(fn (SupportTicket $record): string => self::slaStatus($record))
                    ->color(fn (SupportTicket $record): string => self::isInSupportQueue($record)
                        ? (app(AdminQueueSlaSettings::class)->isOverdue(AdminQueue::Support, $record->created_at) ? 'danger' : 'success')
                        : 'gray'),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query
                ->orderByRaw('CASE WHEN status IN (?, ?) THEN 0 ELSE 1 END', [
                    SupportTicketStatus::Open->value,
                    SupportTicketStatus::InProgress->value,
                ])
                ->orderBy('created_at', 'asc'))
            ->filters([
                OperationalDateFilter::make('created_at'),
                Filter::make('overdue')
                    ->label(__('shared::shared.admin_queue_sla.overdue'))
                    ->query(fn (Builder $query): Builder => $query
                        ->whereIn('status', [SupportTicketStatus::Open->value, SupportTicketStatus::InProgress->value])
                        ->where('created_at', '<=', now('UTC')->subHours(app(AdminQueueSlaSettings::class)->hours(AdminQueue::Support)))),
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
            ->bulkActions([
                ScopedBulkAction::make(
                    'markSelectedInProgress',
                    __('support::support.actions.mark_in_progress'),
                    'update_support::ticket',
                    static::class,
                    function (SupportTicket $record): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof Authenticatable, 403);
                        app(TransitionSupportTicketStatusAction::class)->execute(
                            $record,
                            $actor,
                            SupportTicketStatus::InProgress,
                        );
                    },
                ),
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

    public static function getRelations(): array
    {
        return [InternalRecordNotesRelationManager::class];
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

    private static function isInSupportQueue(SupportTicket $record): bool
    {
        return in_array($record->status, [SupportTicketStatus::Open, SupportTicketStatus::InProgress], true);
    }

    private static function slaStatus(SupportTicket $record): string
    {
        if (! self::isInSupportQueue($record)) {
            return __('shared::shared.admin_queue_sla.not_active');
        }

        return app(AdminQueueSlaSettings::class)->isOverdue(AdminQueue::Support, $record->created_at)
            ? __('shared::shared.admin_queue_sla.overdue')
            : __('shared::shared.admin_queue_sla.within_sla');
    }
}
