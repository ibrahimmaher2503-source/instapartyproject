<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Application\Timeline\DTOs\TimelineCursor;
use App\Modules\Shared\Application\Timeline\DTOs\TimelineEntryDTO;
use App\Modules\Shared\Application\Timeline\DTOs\TimelineFilters;
use App\Modules\Shared\Application\Timeline\DTOs\TimelineMeta;
use App\Modules\Shared\Application\Timeline\DTOs\TimelinePage;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Application\Timeline\TimelineKWayMerger;
use App\Modules\Shared\Application\Timeline\TimelineSourceRegistry;
use App\Modules\Shared\Application\Timeline\TimelineSubjectScope;
use App\Modules\Shared\Domain\Contracts\TranslatableState;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\App;
use Psr\Log\LoggerInterface;

/**
 * Single read-only pipeline for audit timeline rendering.
 *
 * Follows .claude/rules/actions.md:
 * - One public method: execute()
 * - Constructor injection only
 * - Never writes — architecture test AuditTimelineReadOnlyTest enforces this
 * - No DB::transaction (read-only; no writes to wrap)
 */
final class BuildAuditTimelineAction
{
    public function __construct(
        private readonly TimelineSourceRegistry $registry,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(
        Model $subject,
        TimelineAudience $audience,
        ?User $viewer = null,
        ?TimelineCursor $cursor = null,
        ?TimelineFilters $filters = null,
        int $pageSize = 50,
    ): TimelinePage {
        $filters ??= new TimelineFilters;
        $scope = new TimelineSubjectScope($subject, $viewer, $audience);
        $locale = App::getLocale();

        $descriptors = $this->registry->descriptorsFor($subject, $audience);

        $streams = [];
        $legacyLedgerOmittedCount = null;

        foreach ($descriptors as $descriptor) {
            $queryBuilder = ($descriptor->queryBuilder)($subject, $scope, $filters);

            $tsColumn = $descriptor->timestampColumn;

            // Apply cursor: skip rows at or before the cursor position
            if ($cursor !== null) {
                $queryBuilder = $queryBuilder->where(function ($q) use ($cursor, $tsColumn) {
                    $q->where($tsColumn, '>', $cursor->afterOccurredAt)
                        ->orWhere(function ($q2) use ($cursor, $tsColumn) {
                            $q2->where($tsColumn, $cursor->afterOccurredAt)
                                ->where('id', '>', $cursor->afterSourceId);
                        });
                });
            }

            $rows = $queryBuilder
                ->orderBy($tsColumn, 'asc')
                ->orderBy('id', 'asc')
                ->limit($pageSize + 1)
                ->get();

            $stream = [];
            foreach ($rows as $row) {
                $dto = ($descriptor->rowMapper)($row, $audience);

                // Resolve note from JSON translatable column
                if ($dto->note !== null && is_array(json_decode($dto->note, true))) {
                    $dto = $this->resolveNote($dto, $locale);
                }

                // Resolve from_state / to_state via TranslatableState opt-in
                $dto = $this->resolveStates($dto, $locale);

                $stream[] = ['dto' => $dto, 'rank' => $descriptor->canonicalRank];
            }

            $streams[] = $stream;

            // Collect legacy ledger omitted count from withdrawal descriptor meta
            if (isset($rows->legacyLedgerOmittedCount) && $audience === TimelineAudience::Admin) {
                $legacyLedgerOmittedCount = ($legacyLedgerOmittedCount ?? 0) + $rows->legacyLedgerOmittedCount;
            }
        }

        $result = TimelineKWayMerger::merge($streams, $pageSize);
        $entries = $result['entries'];
        $hasMore = $result['hasMore'];

        // Build next cursor from the last entry
        $nextCursor = null;
        if ($hasMore && count($entries) > 0) {
            $last = $entries[count($entries) - 1];
            // sourcePublicId may be null for synth entries; fall back to a surrogate
            $nextCursor = new TimelineCursor(
                afterOccurredAt: $last->occurredAt,
                afterSourceTable: $last->sourceTable,
                afterSourceId: 0, // descriptors that need cursor tracking include the raw id in extra['_id']
            );
        }

        // Vendor audience must never see legacyLedgerOmittedCount
        $meta = new TimelineMeta(
            pageSize: $pageSize,
            hasMore: $hasMore,
            legacyLedgerOmittedCount: $audience === TimelineAudience::Admin ? $legacyLedgerOmittedCount : null,
        );

        return new TimelinePage(entries: $entries, nextCursor: $nextCursor, meta: $meta);
    }

    private function resolveNote(TimelineEntryDTO $dto, string $locale): TimelineEntryDTO
    {
        $decoded = json_decode($dto->note ?? '', associative: true);
        if (! is_array($decoded)) {
            return $dto;
        }

        $resolved = $decoded[$locale] ?? $decoded['en'] ?? $decoded['ar'] ?? null;

        if ($resolved === null) {
            $this->logger->warning('audit_timeline.note_missing_locale', [
                'source_table' => $dto->sourceTable,
                'locale' => $locale,
            ]);
        }

        return new TimelineEntryDTO(
            occurredAt: $dto->occurredAt,
            sourceTable: $dto->sourceTable,
            sourcePublicId: $dto->sourcePublicId,
            actorLabel: $dto->actorLabel,
            actorRole: $dto->actorRole,
            actionKey: $dto->actionKey,
            fromState: $dto->fromState,
            toState: $dto->toState,
            note: $resolved,
            eventKind: $dto->eventKind,
            isAdminOnly: $dto->isAdminOnly,
            extra: $dto->extra,
        );
    }

    private function resolveStates(TimelineEntryDTO $dto, string $locale): TimelineEntryDTO
    {
        $fromLabel = $dto->fromState;
        $toLabel = $dto->toState;
        $changed = false;

        if ($dto->fromState !== null) {
            $resolved = $this->resolveStateLabel($dto->fromState, $dto->extra['_state_class'] ?? null, $locale);
            if ($resolved !== null) {
                $fromLabel = $resolved;
                $changed = true;
            }
        }

        if ($dto->toState !== null) {
            $resolved = $this->resolveStateLabel($dto->toState, $dto->extra['_state_class'] ?? null, $locale);
            if ($resolved !== null) {
                $toLabel = $resolved;
                $changed = true;
            }
        }

        if (! $changed) {
            return $dto;
        }

        return new TimelineEntryDTO(
            occurredAt: $dto->occurredAt,
            sourceTable: $dto->sourceTable,
            sourcePublicId: $dto->sourcePublicId,
            actorLabel: $dto->actorLabel,
            actorRole: $dto->actorRole,
            actionKey: $dto->actionKey,
            fromState: $fromLabel,
            toState: $toLabel,
            note: $dto->note,
            eventKind: $dto->eventKind,
            isAdminOnly: $dto->isAdminOnly,
            extra: $dto->extra,
        );
    }

    private function resolveStateLabel(string $stateName, ?string $stateClass, string $locale): ?string
    {
        if ($stateClass === null || ! class_exists($stateClass)) {
            return null;
        }

        if (! in_array(TranslatableState::class, class_implements($stateClass) ?: [], strict: true)) {
            $this->logger->warning('audit_timeline.untranslated_state', [
                'state_class' => $stateClass,
                'state_name' => $stateName,
            ]);

            return null;
        }

        return $stateClass::label($stateName, $locale);
    }
}
