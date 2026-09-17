<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Timeline;

use App\Modules\Payments\Domain\Models\Payment;
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
 * Registers all timeline sources for the Payment subject.
 *
 * Sources:
 *   rank  5  — payment_attempts   (System, admin-only)
 *   rank 10  — payments synth     (Financial, status-derived event)
 *   rank 20  — refunds            (Financial)
 *   rank 30  — audit_logs         (System)
 */
final class PaymentTimelineDescriptors
{
    public static function register(TimelineSourceRegistry $registry): void
    {
        $registry->register(
            Payment::class,
            self::paymentAttemptsDescriptor(),
            self::paymentSynthDescriptor(),
            self::refundsDescriptor(),
            self::auditLogsDescriptor(),
        );
    }

    // -------------------------------------------------------------------------
    // rank 5 — payment_attempts (admin-only)
    // -------------------------------------------------------------------------

    private static function paymentAttemptsDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'payment_attempts',
            defaultEventKind: TimelineEventKind::System,
            canonicalRank: 5,
            queryBuilder: function (Payment $subject, TimelineSubjectScope $scope, TimelineFilters $filters): Builder {
                return DB::table('payment_attempts')
                    ->where('payment_id', $subject->id)
                    ->select([
                        'id',
                        'payment_id',
                        'attempt_no',
                        'http_status',
                        'request_payload',
                        'response_payload',
                        'created_at as occurred_at',
                    ])
                    ->orderBy('created_at');
            },
            rowMapper: function (object $row, TimelineAudience $audience): TimelineEntryDTO {
                $httpStatus = $row->http_status ?? null;
                $isSuccess = $httpStatus !== null && $httpStatus >= 200 && $httpStatus < 300;
                $actionKey = $isSuccess ? 'payment.attempt.succeeded' : 'payment.attempt.failed';

                $extra = [
                    'attempt_no' => $row->attempt_no,
                    'http_status' => $httpStatus,
                ];

                // Admin gets full payloads; vendor sees nothing (adminOnly = true)
                if ($audience === TimelineAudience::Admin) {
                    $extra['request_payload'] = is_string($row->request_payload ?? null)
                        ? json_decode($row->request_payload, true)
                        : ($row->request_payload ?? null);
                    $extra['response_payload'] = is_string($row->response_payload ?? null)
                        ? json_decode($row->response_payload, true)
                        : ($row->response_payload ?? null);
                }

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->occurred_at)->utc(),
                    sourceTable: 'payment_attempts',
                    sourcePublicId: null, // payment_attempts has no public_id
                    actorLabel: 'System',
                    actorRole: TimelineActorRole::Webhook,
                    actionKey: $actionKey,
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
                    'columns' => ['id', 'payment_id', 'attempt_no', 'http_status', 'request_payload', 'response_payload', 'occurred_at'],
                    'jsonKeys' => ['*'],
                    'stripDeep' => [],
                    'adminOnly' => true,
                ],
                // Vendor rule present but adminOnly=true so it is filtered out by the registry
                'vendor' => [
                    'columns' => [],
                    'jsonKeys' => [],
                    'stripDeep' => [],
                    'adminOnly' => true,
                ],
            ],
        );
    }

    // -------------------------------------------------------------------------
    // rank 10 — payments synth (Financial, status-derived)
    // -------------------------------------------------------------------------

    private static function paymentSynthDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'payments',
            defaultEventKind: TimelineEventKind::Financial,
            canonicalRank: 10,
            queryBuilder: function (Payment $subject, TimelineSubjectScope $scope, TimelineFilters $filters): Builder {
                return DB::table('payments')
                    ->where('id', $subject->id)
                    ->select([
                        'id',
                        'public_id',
                        'gateway',
                        'gateway_ref',
                        'amount_minor',
                        'amount_currency',
                        'method',
                        'status',
                        'captured_at',
                        'failure_code',
                        'failure_message',
                        'correlation_id',
                        'created_at as occurred_at',
                    ]);
            },
            rowMapper: function (object $row, TimelineAudience $audience): TimelineEntryDTO {
                $status = $row->status ?? 'unknown';
                $actionKey = match ($status) {
                    'captured' => 'payment.captured',
                    'failed' => 'payment.failed',
                    'authorized' => 'payment.authorized',
                    'refunded' => 'payment.refunded',
                    'partially_refunded' => 'payment.partially_refunded',
                    'voided' => 'payment.voided',
                    'abandoned' => 'payment.abandoned',
                    default => 'payment.pending',
                };

                $occurredAt = $row->captured_at !== null
                    ? CarbonImmutable::parse($row->captured_at)->utc()
                    : CarbonImmutable::parse($row->occurred_at)->utc();

                // Base extra visible to all audiences
                $extra = [
                    'amount_minor' => $row->amount_minor,
                    'amount_currency' => $row->amount_currency,
                    'method' => $row->method,
                    'status' => $status,
                ];

                // Admin-only sensitive fields
                if ($audience === TimelineAudience::Admin) {
                    $extra['gateway'] = $row->gateway;
                    $extra['gateway_ref'] = $row->gateway_ref;
                    $extra['failure_code'] = $row->failure_code ?? null;
                    $extra['failure_message'] = is_string($row->failure_message ?? null)
                        ? json_decode($row->failure_message, true)
                        : ($row->failure_message ?? null);
                    $extra['correlation_id'] = $row->correlation_id ?? null;
                }

                return new TimelineEntryDTO(
                    occurredAt: $occurredAt,
                    sourceTable: 'payments',
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
                    'columns' => ['id', 'public_id', 'gateway', 'gateway_ref', 'amount_minor', 'amount_currency', 'method', 'status', 'captured_at', 'failure_code', 'failure_message', 'correlation_id', 'occurred_at'],
                    'jsonKeys' => ['*'],
                    'stripDeep' => [],
                    'adminOnly' => false,
                ],
                // Vendors see only non-sensitive payment summary — gateway details are stripped
                'vendor' => [
                    'columns' => ['id', 'public_id', 'amount_minor', 'amount_currency', 'method', 'status', 'captured_at', 'occurred_at'],
                    'jsonKeys' => [],
                    'stripDeep' => ['gateway', 'gateway_ref', 'failure_code', 'failure_message', 'correlation_id'],
                    'adminOnly' => false,
                ],
            ],
        );
    }

    // -------------------------------------------------------------------------
    // rank 20 — refunds (Financial)
    // -------------------------------------------------------------------------

    private static function refundsDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'refunds',
            defaultEventKind: TimelineEventKind::Financial,
            canonicalRank: 20,
            queryBuilder: function (Payment $subject, TimelineSubjectScope $scope, TimelineFilters $filters): Builder {
                return DB::table('refunds')
                    ->where('payment_id', $subject->id)
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
                        'created_at as occurred_at',
                    ])
                    ->orderBy('created_at');
            },
            rowMapper: function (object $row, TimelineAudience $audience): TimelineEntryDTO {
                $status = $row->status ?? 'pending';
                $actionKey = match ($status) {
                    'completed' => 'payment.refund.completed',
                    'processing' => 'payment.refund.processing',
                    'failed' => 'payment.refund.failed',
                    default => 'payment.refund.initiated',
                };

                $occurredAt = ($row->processed_at !== null && in_array($status, ['completed', 'failed'], true))
                    ? CarbonImmutable::parse($row->processed_at)->utc()
                    : CarbonImmutable::parse($row->occurred_at)->utc();

                $extra = [
                    'amount_minor' => $row->amount_minor,
                    'amount_currency' => $row->amount_currency,
                    'status' => $status,
                    'reason_code' => $row->reason_code ?? null,
                ];

                if ($audience === TimelineAudience::Admin) {
                    $extra['gateway_ref'] = $row->gateway_ref ?? null;
                    $extra['reason_notes'] = is_string($row->reason_notes ?? null)
                        ? json_decode($row->reason_notes, true)
                        : ($row->reason_notes ?? null);
                    $extra['initiated_by'] = $row->initiated_by ?? null;
                    $extra['booking_id'] = $row->booking_id ?? null;
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
                    'columns' => ['id', 'public_id', 'payment_id', 'booking_id', 'amount_minor', 'amount_currency', 'reason_code', 'reason_notes', 'gateway_ref', 'status', 'initiated_by', 'processed_at', 'occurred_at'],
                    'jsonKeys' => ['*'],
                    'stripDeep' => [],
                    'adminOnly' => false,
                ],
                'vendor' => [
                    'columns' => ['id', 'public_id', 'amount_minor', 'amount_currency', 'reason_code', 'status', 'processed_at', 'occurred_at'],
                    'jsonKeys' => [],
                    'stripDeep' => ['gateway_ref', 'reason_notes', 'initiated_by', 'booking_id'],
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
            queryBuilder: function (Payment $subject, TimelineSubjectScope $scope, TimelineFilters $filters): Builder {
                return DB::table('audit_logs')
                    ->where('auditable_type', Payment::class)
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
