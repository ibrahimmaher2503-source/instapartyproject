<?php

declare(strict_types=1);

namespace App\Modules\Shared\Filament\Infolists\Components;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Application\Actions\BuildAuditTimelineAction;
use App\Modules\Shared\Application\Timeline\DTOs\TimelineFilters;
use App\Modules\Shared\Application\Timeline\DTOs\TimelinePage;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Application\Timeline\Enums\TimelineEventKind;
use Closure;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Support\Enums\FontWeight;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;

class AuditTimelineSection extends Section
{
    protected TimelineAudience $audience = TimelineAudience::Admin;

    protected int $pageSize = 50;

    public static function make(Htmlable|Closure|array|string|null $heading = null): static
    {
        /** @var static $section */
        $section = parent::make($heading ?? __('audit_timeline.section_title'));

        $section
            ->icon('heroicon-o-clock')
            ->collapsible()
            ->schema([
                RepeatableEntry::make('audit_timeline_entries')
                    ->hiddenLabel()
                    ->state(fn (?Model $record) => $section->resolveEntries($record))
                    ->schema([
                        TextEntry::make('action_label')
                            ->hiddenLabel()
                            ->weight(FontWeight::Bold)
                            ->columnSpanFull(),
                        TextEntry::make('event_kind')
                            ->hiddenLabel()
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                TimelineEventKind::StateChange->value => 'info',
                                TimelineEventKind::Financial->value => 'success',
                                TimelineEventKind::Moderation->value => 'danger',
                                TimelineEventKind::Note->value => 'warning',
                                TimelineEventKind::Document->value => 'primary',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn (?string $state): string => $state !== null
                                ? __("audit_timeline.event_kind.{$state}")
                                : ''),
                        TextEntry::make('state_transition')
                            ->hiddenLabel()
                            ->badge()
                            ->color('gray')
                            ->visible(fn (?string $state): bool => filled($state)),
                        TextEntry::make('actor_label')
                            ->label(__('audit_timeline.fields.actor'))
                            ->icon('heroicon-o-user'),
                        TextEntry::make('occurred_at')
                            ->label(__('audit_timeline.fields.occurred_at'))
                            ->dateTime()
                            ->since(),
                        TextEntry::make('note')
                            ->label(__('audit_timeline.fields.note'))
                            ->columnSpanFull()
                            ->visible(fn (?string $state): bool => filled($state)),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);

        return $section;
    }

    public function audience(TimelineAudience $audience): static
    {
        $this->audience = $audience;

        return $this;
    }

    public function pageSize(int $pageSize): static
    {
        $this->pageSize = $pageSize;

        return $this;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function resolveEntries(?Model $record): array
    {
        if ($record === null) {
            return [];
        }

        /** @var User|null $viewer */
        $viewer = $this->audience === TimelineAudience::Admin ? null : auth()->user();

        /** @var TimelinePage $timeline */
        $timeline = app(BuildAuditTimelineAction::class)->execute(
            subject: $record,
            audience: $this->audience,
            viewer: $viewer,
            cursor: null,
            filters: new TimelineFilters,
            pageSize: $this->pageSize,
        );

        $locale = app()->getLocale();
        $actionKeys = __('audit_timeline.action_keys', [], $locale);
        $actorRoles = __('audit_timeline.actor_role', [], $locale);
        $entries = [];

        foreach ($timeline->entries as $entry) {
            $from = $entry->fromState;
            $to = $entry->toState;

            $transition = match (true) {
                $from !== null && $to !== null => "{$from} → {$to}",
                $to !== null => "→ {$to}",
                $from !== null => "{$from} →",
                default => null,
            };

            $actionLabel = (is_array($actionKeys) ? ($actionKeys[$entry->actionKey] ?? null) : null) ?? $entry->actionKey;
            $actorRole = is_array($actorRoles) ? ($actorRoles[$entry->actorRole->value] ?? $entry->actorRole->value) : $entry->actorRole->value;

            $entries[] = [
                'action_label' => $actionLabel,
                'event_kind' => $entry->eventKind->value,
                'state_transition' => $transition,
                'actor_label' => $entry->actorLabel.' · '.$actorRole,
                'occurred_at' => $entry->occurredAt,
                'note' => $entry->note,
            ];
        }

        return $entries;
    }
}
