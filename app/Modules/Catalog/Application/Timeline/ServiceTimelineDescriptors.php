<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Timeline;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\ServiceState;
use App\Modules\Shared\Application\Timeline\DTOs\TimelineEntryDTO;
use App\Modules\Shared\Application\Timeline\DTOs\TimelineFilters;
use App\Modules\Shared\Application\Timeline\Enums\TimelineActorRole;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Application\Timeline\Enums\TimelineEventKind;
use App\Modules\Shared\Application\Timeline\SourceTableDescriptor;
use App\Modules\Shared\Application\Timeline\TimelineSubjectScope;
use App\Modules\Shared\Domain\Models\AuditLog;
use App\Modules\Shared\Domain\Models\StateTransition;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ServiceTimelineDescriptors
{
    /** @return array<int, SourceTableDescriptor> */
    public static function all(): array
    {
        return [
            self::stateTransitionsDescriptor(),
            self::auditLogsDescriptor(),
            self::activityLogDescriptor(),
        ];
    }

    private static function stateTransitionsDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'state_transitions',
            defaultEventKind: TimelineEventKind::StateChange,
            canonicalRank: 10,
            queryBuilder: function (Service $subject, TimelineSubjectScope $scope, TimelineFilters $filters) {
                $query = StateTransition::query()
                    ->where('transitionable_type', Service::class)
                    ->where('transitionable_id', $subject->getKey())
                    ->with('triggeredByUser:id,name');

                if ($filters->eventKind !== null && $filters->eventKind !== TimelineEventKind::StateChange) {
                    $query->whereRaw('0 = 1');
                }

                if ($filters->actorRole !== null) {
                    $query->where('trigger_kind', $filters->actorRole->value);
                }

                return $query;
            },
            rowMapper: function (StateTransition $row, TimelineAudience $audience): TimelineEntryDTO {
                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->created_at),
                    sourceTable: 'state_transitions',
                    sourcePublicId: null,
                    actorLabel: $row->triggeredByUser?->name ?? 'System',
                    actorRole: self::resolveActorRole($row->trigger_kind),
                    actionKey: 'service.state_changed',
                    fromState: $row->from_state,
                    toState: $row->to_state,
                    note: $row->reason,
                    eventKind: TimelineEventKind::StateChange,
                    isAdminOnly: false,
                    extra: [
                        '_state_class' => ServiceState::class,
                        '_id' => $row->id,
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
                    'columns' => ['from_state', 'to_state', 'trigger_kind', 'reason', 'created_at'],
                    'jsonKeys' => [],
                    'stripDeep' => [],
                    'adminOnly' => false,
                ],
            ],
        );
    }

    private static function auditLogsDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'audit_logs',
            defaultEventKind: TimelineEventKind::System,
            canonicalRank: 30,
            queryBuilder: function (Service $subject, TimelineSubjectScope $scope, TimelineFilters $filters) {
                $query = AuditLog::query()
                    ->where('auditable_type', Service::class)
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

    private static function activityLogDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'activity_log',
            defaultEventKind: TimelineEventKind::System,
            canonicalRank: 40,
            queryBuilder: function (Service $subject, TimelineSubjectScope $scope, TimelineFilters $filters) {
                return DB::table('activity_log')
                    ->where('subject_type', Service::class)
                    ->where('subject_id', $subject->getKey());
            },
            rowMapper: function (object $row, TimelineAudience $audience): TimelineEntryDTO {
                $causerName = property_exists($row, 'causer_name') ? $row->causer_name : null;

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->created_at),
                    sourceTable: 'activity_log',
                    sourcePublicId: null,
                    actorLabel: $causerName ?? 'System',
                    actorRole: TimelineActorRole::Admin,
                    actionKey: 'service.profile_updated',
                    fromState: null,
                    toState: null,
                    note: property_exists($row, 'description') ? $row->description : null,
                    eventKind: TimelineEventKind::System,
                    isAdminOnly: true,
                    extra: [
                        '_id' => $row->id,
                        'properties' => property_exists($row, 'properties') ? json_decode((string) $row->properties, true) : [],
                    ],
                );
            },
            visibilityRules: [
                'admin' => [
                    'columns' => ['*'],
                    'jsonKeys' => ['*'],
                    'stripDeep' => [],
                    'adminOnly' => true,
                ],
                'vendor' => [
                    'columns' => [],
                    'jsonKeys' => [],
                    'stripDeep' => [],
                    'adminOnly' => true,
                ],
            ],
        );
    }

    /**
     * Maps a trigger_kind string (from state_transitions) to a TimelineActorRole enum case.
     * Falls back to System if the value is unrecognised or null.
     */
    private static function resolveActorRole(?string $triggerKind): TimelineActorRole
    {
        return match ($triggerKind) {
            'admin', 'admin_override' => TimelineActorRole::Admin,
            'vendor' => TimelineActorRole::Vendor,
            'customer' => TimelineActorRole::Customer,
            default => TimelineActorRole::System,
        };
    }
}
