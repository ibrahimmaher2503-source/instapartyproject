<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Timeline;

use App\Modules\Catalog\Domain\Models\ServiceChangeRequest;
use App\Modules\Shared\Application\Timeline\DTOs\TimelineEntryDTO;
use App\Modules\Shared\Application\Timeline\DTOs\TimelineFilters;
use App\Modules\Shared\Application\Timeline\Enums\TimelineActorRole;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Application\Timeline\Enums\TimelineEventKind;
use App\Modules\Shared\Application\Timeline\SourceTableDescriptor;
use App\Modules\Shared\Application\Timeline\TimelineSubjectScope;
use App\Modules\Shared\Domain\Models\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ChangeRequestTimelineDescriptors
{
    /**
     * Terminal statuses that produce a second synthetic "decision" event row.
     *
     * @var list<string>
     */
    private const TERMINAL_STATUSES = [
        'approved',
        'rejected',
        'awaiting_clarification',
        'cancelled_vendor_suspended',
        'cancelled_service_unavailable',
    ];

    /** @return array<int, SourceTableDescriptor> */
    public static function all(): array
    {
        return [
            self::changeRequestsDescriptor(),
            self::auditLogsDescriptor(),
        ];
    }

    private static function changeRequestsDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'service_change_requests',
            defaultEventKind: TimelineEventKind::Moderation,
            canonicalRank: 10,
            queryBuilder: function (ServiceChangeRequest $subject, TimelineSubjectScope $scope, TimelineFilters $filters) {
                /*
                 * Synthesise two timeline rows from one DB record:
                 *
                 * Row 1 — submission event (occurred_at = created_at)
                 * Row 2 — decision event   (occurred_at = decided_at, only when terminal)
                 *
                 * Both rows carry the same id/public_id/status so the rowMapper can
                 * tell them apart via the `event_slot` synthetic column.
                 *
                 * Using a UNION so the k-way merger sees them as separate cursor entries.
                 */
                $submissionRow = DB::table('service_change_requests')
                    ->where('id', $subject->getKey())
                    ->select([
                        'id',
                        'public_id',
                        'status',
                        'created_at as occurred_at',
                        DB::raw("'submitted' as event_slot"),
                        'decided_at',
                        'clarification_round',
                    ]);

                // Only emit a decision row when the request has reached a terminal/clarification status
                $decisionRow = DB::table('service_change_requests')
                    ->where('id', $subject->getKey())
                    ->whereIn('status', self::TERMINAL_STATUSES)
                    ->whereNotNull('decided_at')
                    ->select([
                        'id',
                        'public_id',
                        'status',
                        'decided_at as occurred_at',
                        DB::raw("'decided' as event_slot"),
                        'decided_at',
                        'clarification_round',
                    ]);

                if ($filters->eventKind !== null && $filters->eventKind !== TimelineEventKind::Moderation) {
                    // Return an empty query — wrong event kind requested
                    return DB::table('service_change_requests')->whereRaw('0 = 1')->select(['id', 'public_id', 'status', 'created_at as occurred_at', DB::raw("'submitted' as event_slot"), 'decided_at', 'clarification_round']);
                }

                return $submissionRow->union($decisionRow);
            },
            rowMapper: function (object $row, TimelineAudience $audience): TimelineEntryDTO {
                $eventSlot = $row->event_slot ?? 'submitted';

                if ($eventSlot === 'submitted') {
                    return new TimelineEntryDTO(
                        occurredAt: CarbonImmutable::parse($row->occurred_at),
                        sourceTable: 'service_change_requests',
                        sourcePublicId: $row->public_id ?? null,
                        actorLabel: 'Vendor',
                        actorRole: TimelineActorRole::Vendor,
                        actionKey: 'service.change_request.submitted',
                        fromState: null,
                        toState: 'pending',
                        note: null,
                        eventKind: TimelineEventKind::Moderation,
                        isAdminOnly: false,
                        extra: [
                            '_id' => $row->id,
                            'status' => $row->status,
                        ],
                    );
                }

                // Decision event — map status to action key
                $actionKey = match ($row->status) {
                    'approved' => 'service.change_request.approved',
                    'rejected' => 'service.change_request.rejected',
                    'awaiting_clarification' => 'service.change_request.clarification_requested',
                    'cancelled_vendor_suspended' => 'service.change_request.cancelled',
                    'cancelled_service_unavailable' => 'service.change_request.cancelled',
                    default => 'service.change_request.decided',
                };

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->occurred_at),
                    sourceTable: 'service_change_requests',
                    sourcePublicId: $row->public_id ?? null,
                    actorLabel: 'Admin',
                    actorRole: TimelineActorRole::Admin,
                    actionKey: $actionKey,
                    fromState: 'pending',
                    toState: $row->status,
                    note: null,
                    eventKind: TimelineEventKind::Moderation,
                    isAdminOnly: false,
                    extra: [
                        '_id' => $row->id,
                        'status' => $row->status,
                        'clarification_round' => $row->clarification_round,
                    ],
                );
            },
            visibilityRules: [
                'admin' => [
                    'columns' => ['*'],
                    'jsonKeys' => ['*'],
                    'stripDeep' => [],
                    'adminOnly' => false,
                ],
                'vendor' => [
                    'columns' => ['public_id', 'status', 'occurred_at', 'event_slot', 'clarification_round'],
                    'jsonKeys' => [],
                    'stripDeep' => [],
                    'adminOnly' => false,
                ],
            ],
            // Union query — ORDER BY can only reference result-set columns (the alias).
            timestampColumn: 'occurred_at',
        );
    }

    private static function auditLogsDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'audit_logs',
            defaultEventKind: TimelineEventKind::System,
            canonicalRank: 30,
            queryBuilder: function (ServiceChangeRequest $subject, TimelineSubjectScope $scope, TimelineFilters $filters) {
                $query = AuditLog::query()
                    ->where('auditable_type', ServiceChangeRequest::class)
                    ->where('auditable_id', $subject->getKey());

                if ($filters->eventKind !== null && $filters->eventKind !== TimelineEventKind::System) {
                    $query->whereRaw('0 = 1');
                }

                if ($filters->actorRole !== null) {
                    if ($filters->actorRole !== TimelineActorRole::System && $filters->actorRole !== TimelineActorRole::Admin) {
                        $query->whereRaw('0 = 1');
                    }
                }

                return $query;
            },
            rowMapper: function (AuditLog $row, TimelineAudience $audience): TimelineEntryDTO {
                $changesNote = null;
                if (! empty($row->changes)) {
                    $changesNote = is_array($row->changes)
                        ? implode(', ', array_keys($row->changes))
                        : (string) $row->changes;
                }

                return new TimelineEntryDTO(
                    occurredAt: $row->created_at instanceof CarbonImmutable
                        ? $row->created_at
                        : CarbonImmutable::parse($row->created_at),
                    sourceTable: 'audit_logs',
                    sourcePublicId: $row->public_id ?? null,
                    actorLabel: $row->actor?->name ?? 'System',
                    actorRole: $row->user_id !== null ? TimelineActorRole::Admin : TimelineActorRole::System,
                    actionKey: $row->action,
                    fromState: null,
                    toState: null,
                    note: $changesNote,
                    eventKind: TimelineEventKind::System,
                    isAdminOnly: false,
                    extra: [
                        '_id' => $row->id,
                        'changes' => $row->changes,
                    ],
                );
            },
            visibilityRules: [
                'admin' => [
                    'columns' => ['*'],
                    'jsonKeys' => ['*'],
                    'stripDeep' => [],
                    'adminOnly' => false,
                ],
                'vendor' => [
                    'columns' => ['action', 'created_at'],
                    'jsonKeys' => [],
                    'stripDeep' => ['changes'],
                    'adminOnly' => false,
                ],
            ],
        );
    }
}
