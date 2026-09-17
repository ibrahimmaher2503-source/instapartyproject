<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Timeline;

use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Shared\Application\Timeline\DTOs\TimelineEntryDTO;
use App\Modules\Shared\Application\Timeline\DTOs\TimelineFilters;
use App\Modules\Shared\Application\Timeline\Enums\TimelineActorRole;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Application\Timeline\Enums\TimelineEventKind;
use App\Modules\Shared\Application\Timeline\SourceTableDescriptor;
use App\Modules\Shared\Application\Timeline\TimelineSubjectScope;
use App\Modules\Shared\Domain\Models\AuditLog;
use Carbon\CarbonImmutable;

final class BookingModificationTimelineDescriptors
{
    /** @return array<int, SourceTableDescriptor> */
    public static function all(): array
    {
        return [
            self::modificationsDescriptor(),
            self::auditLogsDescriptor(),
        ];
    }

    /**
     * Synthesised status history from the booking_modifications row itself.
     * For a single modification subject, this surfaces the lifecycle milestones
     * (created → pending → accepted/rejected/withdrawn/expired).
     */
    private static function modificationsDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'booking_modifications',
            defaultEventKind: TimelineEventKind::Moderation,
            canonicalRank: 10,
            queryBuilder: static function (BookingModification $subject, TimelineSubjectScope $scope, TimelineFilters $filters) {
                // The subject IS the modification; we query its own record so that
                // the row mapper can emit synthetic milestone entries.
                return BookingModification::where('id', $subject->id)
                    ->with('proposedBy:id,name')
                    ->orderBy('created_at');
            },
            rowMapper: static function (BookingModification $row, TimelineAudience $audience): TimelineEntryDTO {
                $proposer = $row->proposedBy;

                $actorLabel = $proposer?->name ?? 'Unknown';
                $actorRole = TimelineActorRole::Vendor;

                $extra = [
                    'proposal_kind' => $row->proposal_kind?->value,
                    'status' => $row->status?->value,
                    'booking_vendor_id' => $row->booking_vendor_id,
                    'expires_at' => $row->expires_at?->toIso8601String(),
                    'customer_decision_at' => $row->customer_decision_at?->toIso8601String(),
                ];

                if ($audience === TimelineAudience::Admin) {
                    $extra['diff_snapshot'] = $row->diff_snapshot;
                    $extra['vendor_explanation'] = $row->vendor_explanation;
                    $extra['proposed_by'] = $row->proposed_by;
                }

                if ($audience === TimelineAudience::Vendor) {
                    $extra['vendor_explanation'] = $row->vendor_explanation;
                }

                // Determine the most meaningful timestamp:
                // If a customer decision was made, use that; otherwise use created_at.
                $occurredAt = $row->customer_decision_at !== null
                    ? CarbonImmutable::parse($row->customer_decision_at)
                    : CarbonImmutable::parse($row->created_at);

                return new TimelineEntryDTO(
                    occurredAt: $occurredAt,
                    sourceTable: 'booking_modifications',
                    sourcePublicId: $row->public_id,
                    actorLabel: $actorLabel,
                    actorRole: $actorRole,
                    actionKey: 'booking_modification.'.($row->status?->value ?? 'unknown'),
                    fromState: null,
                    toState: $row->status?->value,
                    note: is_array($row->vendor_explanation)
                        ? ($row->vendor_explanation['en'] ?? null)
                        : null,
                    eventKind: TimelineEventKind::Moderation,
                    isAdminOnly: false,
                    extra: $extra,
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
                    'columns' => ['public_id', 'booking_vendor_id', 'proposal_kind', 'status', 'expires_at', 'customer_decision_at', 'created_at'],
                    'jsonKeys' => ['vendor_explanation.en', 'vendor_explanation.ar'],
                    'stripDeep' => ['diff_snapshot', 'proposed_by'],
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
            queryBuilder: static function (BookingModification $subject, TimelineSubjectScope $scope, TimelineFilters $filters) {
                return AuditLog::where('auditable_type', BookingModification::class)
                    ->where('auditable_id', $subject->id)
                    ->with('actor:id,name')
                    ->orderBy('created_at');
            },
            rowMapper: static function (AuditLog $row, TimelineAudience $audience): TimelineEntryDTO {
                $actor = $row->actor;

                $actorLabel = $actor?->name ?? 'System';
                $actorRole = $row->user_id !== null ? TimelineActorRole::Admin : TimelineActorRole::System;

                $extra = [
                    'action' => $row->action,
                ];

                if ($audience === TimelineAudience::Admin) {
                    $extra['changes'] = $row->changes;
                    $extra['ip_address'] = $row->ip_address ?? null;
                    $extra['user_agent'] = $row->user_agent ?? null;
                }

                if ($audience === TimelineAudience::Vendor) {
                    $changes = $row->changes ?? [];
                    $extra['changes'] = [
                        'from' => $changes['from'] ?? null,
                        'to' => $changes['to'] ?? null,
                        'vendor_note' => $changes['vendor_note'] ?? null,
                    ];
                }

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->created_at),
                    sourceTable: 'audit_logs',
                    sourcePublicId: null,
                    actorLabel: $actorLabel,
                    actorRole: $actorRole,
                    actionKey: 'audit.'.($row->action ?? 'unknown'),
                    fromState: null,
                    toState: null,
                    note: null,
                    eventKind: TimelineEventKind::System,
                    isAdminOnly: false,
                    extra: $extra,
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
                    'columns' => ['action', 'created_at', 'user_id'],
                    'jsonKeys' => ['changes.from', 'changes.to', 'changes.vendor_note.en', 'changes.vendor_note.ar'],
                    'stripDeep' => ['changes.internal_admin_note', 'changes.ip_address', 'changes.user_agent', 'changes.diff.private'],
                    'adminOnly' => false,
                ],
            ],
        );
    }
}
