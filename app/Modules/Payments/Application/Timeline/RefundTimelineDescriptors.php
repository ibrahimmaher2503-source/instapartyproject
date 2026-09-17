<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Timeline;

use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Shared\Application\Timeline\DTOs\TimelineEntryDTO;
use App\Modules\Shared\Application\Timeline\DTOs\TimelineFilters;
use App\Modules\Shared\Application\Timeline\Enums\TimelineActorRole;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Application\Timeline\Enums\TimelineEventKind;
use App\Modules\Shared\Application\Timeline\SourceTableDescriptor;
use App\Modules\Shared\Application\Timeline\TimelineSourceRegistry;
use App\Modules\Shared\Application\Timeline\TimelineSubjectScope;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Registers all timeline sources for the Refund subject.
 *
 * Sources:
 *   rank 10 — refunds synth  (Financial, status-derived event)
 *   rank 30 — audit_logs     (System)
 */
final class RefundTimelineDescriptors
{
    public static function register(TimelineSourceRegistry $registry): void
    {
        $registry->register(
            Refund::class,
            self::refundSynthDescriptor(),
            self::auditLogsDescriptor(),
        );
    }

    // -------------------------------------------------------------------------
    // rank 10 — refunds synth (Financial)
    // -------------------------------------------------------------------------

    private static function refundSynthDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'refunds',
            defaultEventKind: TimelineEventKind::Financial,
            canonicalRank: 10,
            queryBuilder: function (Refund $subject, TimelineSubjectScope $scope, TimelineFilters $filters): Builder {
                return DB::table('refunds')
                    ->where('id', $subject->id)
                    ->select([
                        'id',
                        'public_id',
                        'payment_id',
                        'booking_id',
                        'amount_minor',
                        'amount_currency',
                        'reason_code',
                        'reason_notes',
                        'gateway_ref',
                        'status',
                        'initiated_by',
                        'processed_at',
                        // Phase 4.9 additions
                        'ledger_group_id',
                        'idempotency_key',
                        'created_at as occurred_at',
                    ]);
            },
            rowMapper: function (object $row, TimelineAudience $audience): TimelineEntryDTO {
                $status = $row->status ?? 'pending';
                $actionKey = match ($status) {
                    'completed' => 'refund.completed',
                    'processing' => 'refund.processing',
                    'failed' => 'refund.failed',
                    default => 'refund.initiated',
                };

                $occurredAt = ($row->processed_at !== null && in_array($status, ['completed', 'failed'], true))
                    ? CarbonImmutable::parse($row->processed_at)->utc()
                    : CarbonImmutable::parse($row->occurred_at)->utc();

                // Base extra for all audiences
                $extra = [
                    'amount_minor' => $row->amount_minor,
                    'amount_currency' => $row->amount_currency,
                    'status' => $status,
                    'reason_code' => $row->reason_code ?? null,
                ];

                // Admin sees full detail
                if ($audience === TimelineAudience::Admin) {
                    $extra['payment_id'] = $row->payment_id ?? null;
                    $extra['booking_id'] = $row->booking_id ?? null;
                    $extra['gateway_ref'] = $row->gateway_ref ?? null;
                    $extra['initiated_by'] = $row->initiated_by ?? null;
                    $extra['reason_notes'] = is_string($row->reason_notes ?? null)
                        ? json_decode($row->reason_notes, true)
                        : ($row->reason_notes ?? null);
                    $extra['ledger_group_id'] = $row->ledger_group_id ?? null;
                    $extra['idempotency_key'] = $row->idempotency_key ?? null;
                }

                return new TimelineEntryDTO(
                    occurredAt: $occurredAt,
                    sourceTable: 'refunds',
                    sourcePublicId: $row->public_id ?? null,
                    actorLabel: 'System',
                    actorRole: TimelineActorRole::System,
                    actionKey: $actionKey,
                    fromState: null,
                    toState: $status,
                    note: null,
                    eventKind: TimelineEventKind::Financial,
                    isAdminOnly: false,
                    extra: $extra,
                );
            },
            visibilityRules: [
                'admin' => [
                    'columns' => ['id', 'public_id', 'payment_id', 'booking_id', 'amount_minor', 'amount_currency', 'reason_code', 'reason_notes', 'gateway_ref', 'status', 'initiated_by', 'processed_at', 'ledger_group_id', 'idempotency_key', 'occurred_at'],
                    'jsonKeys' => ['*'],
                    'stripDeep' => [],
                    'adminOnly' => false,
                ],
                'vendor' => [
                    'columns' => ['id', 'public_id', 'amount_minor', 'amount_currency', 'reason_code', 'status', 'processed_at', 'occurred_at'],
                    'jsonKeys' => [],
                    'stripDeep' => ['payment_id', 'booking_id', 'gateway_ref', 'initiated_by', 'reason_notes', 'ledger_group_id', 'idempotency_key'],
                    'adminOnly' => false,
                ],
            ],
        );
    }

    // -------------------------------------------------------------------------
    // rank 30 — audit_logs (System)
    // -------------------------------------------------------------------------

    private static function auditLogsDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'audit_logs',
            defaultEventKind: TimelineEventKind::System,
            canonicalRank: 30,
            queryBuilder: function (Refund $subject, TimelineSubjectScope $scope, TimelineFilters $filters): Builder {
                return DB::table('audit_logs')
                    ->where('auditable_type', Refund::class)
                    ->where('auditable_id', $subject->id)
                    ->select([
                        'id',
                        'public_id',
                        'user_id',
                        'action',
                        'changes',
                        'created_at as occurred_at',
                    ])
                    ->orderBy('created_at');
            },
            rowMapper: function (object $row, TimelineAudience $audience): TimelineEntryDTO {
                $extra = [];

                if ($audience === TimelineAudience::Admin) {
                    $extra['user_id'] = $row->user_id ?? null;
                    $extra['changes'] = is_string($row->changes ?? null)
                        ? json_decode($row->changes, true)
                        : ($row->changes ?? null);
                }

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->occurred_at)->utc(),
                    sourceTable: 'audit_logs',
                    sourcePublicId: $row->public_id ?? null,
                    actorLabel: 'Admin',
                    actorRole: TimelineActorRole::Admin,
                    actionKey: $row->action,
                    fromState: null,
                    toState: null,
                    note: null,
                    eventKind: TimelineEventKind::System,
                    isAdminOnly: true,
                    extra: $extra,
                );
            },
            visibilityRules: [
                'admin' => [
                    'columns' => ['id', 'public_id', 'user_id', 'action', 'changes', 'occurred_at'],
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
}
