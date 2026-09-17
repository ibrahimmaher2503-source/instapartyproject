<?php

declare(strict_types=1);

namespace App\Modules\Settlement\Application\Timeline;

use App\Modules\Settlement\Domain\Models\Withdrawal;
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
 * Registers all timeline sources for the Withdrawal subject.
 *
 * Sources (one row per audit timestamp column, plus ledger and audit log):
 *   rank 10 — withdrawals#requested  (Financial, requested_at column)
 *   rank 11 — withdrawals#approved   (Financial, approved_at column)
 *   rank 12 — withdrawals#paid       (Financial, paid_at column)
 *   rank 13 — withdrawals#rejected   (Financial, processed_at column when status=rejected)
 *   rank 20 — wallet_ledger correlated  (Financial)
 *   rank 30 — audit_logs               (System)
 *
 * Visibility rules:
 *   - Admin sees everything including ledger correlation ids and admin-only meta.
 *   - Vendor sees: amount, currency, status, timestamps, admin_payment_note.
 *   - Vendor NEVER sees: legacy_ledger_omitted_count (computed at action level in TimelineMeta),
 *     ledger entry internal IDs, idempotency keys.
 */
final class WithdrawalTimelineDescriptors
{
    public static function register(TimelineSourceRegistry $registry): void
    {
        $registry->register(
            Withdrawal::class,
            self::requestedDescriptor(),
            self::approvedDescriptor(),
            self::paidDescriptor(),
            self::rejectedDescriptor(),
            self::walletLedgerDescriptor(),
            self::auditLogsDescriptor(),
        );
    }

    // -------------------------------------------------------------------------
    // Shared helper: build common extra fields from a withdrawal row
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    private static function baseExtra(object $row, TimelineAudience $audience): array
    {
        $extra = [
            'requested_amount_minor' => $row->requested_amount_minor,
            'requested_amount_currency' => $row->requested_amount_currency,
            'status' => $row->status,
        ];

        if ($audience === TimelineAudience::Admin) {
            $extra['vendor_profile_id'] = $row->vendor_profile_id ?? null;
            $extra['requested_by_user_id'] = $row->requested_by_user_id ?? null;
            $extra['idempotency_key'] = $row->idempotency_key ?? null;
            $extra['reserved_ledger_entry_id'] = $row->reserved_ledger_entry_id ?? null;
            $extra['settled_ledger_entry_id'] = $row->settled_ledger_entry_id ?? null;
            $extra['rejected_ledger_entry_id'] = $row->rejected_ledger_entry_id ?? null;
        }

        return $extra;
    }

    // -------------------------------------------------------------------------
    // rank 10 — withdrawals#requested
    // -------------------------------------------------------------------------

    private static function requestedDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'withdrawals#requested',
            defaultEventKind: TimelineEventKind::Financial,
            canonicalRank: 10,
            queryBuilder: function (Withdrawal $subject, TimelineSubjectScope $scope, TimelineFilters $filters): Builder {
                $query = DB::table('withdrawals')
                    ->where('id', $subject->id)
                    ->whereNotNull('requested_at')
                    ->select([
                        'id',
                        'public_id',
                        'vendor_profile_id',
                        'requested_amount_minor',
                        'requested_amount_currency',
                        'status',
                        'requested_by_user_id',
                        'idempotency_key',
                        'reserved_ledger_entry_id',
                        'settled_ledger_entry_id',
                        'rejected_ledger_entry_id',
                        'requested_at as occurred_at',
                    ]);

                // Defensive vendor constraint: vendor can only see their own withdrawal
                if ($scope->getAudience() === TimelineAudience::Vendor
                    && $scope->getViewerVendorProfileId() !== null) {
                    $query->where('vendor_profile_id', $scope->getViewerVendorProfileId());
                }

                return $query;
            },
            rowMapper: function (object $row, TimelineAudience $audience): TimelineEntryDTO {
                $extra = self::baseExtra($row, $audience);

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->occurred_at)->utc(),
                    sourceTable: 'withdrawals#requested',
                    sourcePublicId: $row->public_id ?? null,
                    actorLabel: 'Vendor',
                    actorRole: TimelineActorRole::Vendor,
                    actionKey: 'withdrawal.requested',
                    fromState: null,
                    toState: 'pending',
                    note: null,
                    eventKind: TimelineEventKind::Financial,
                    isAdminOnly: false,
                    extra: $extra,
                );
            },
            visibilityRules: [
                'admin' => [
                    'columns' => ['id', 'public_id', 'vendor_profile_id', 'requested_amount_minor', 'requested_amount_currency', 'status', 'requested_by_user_id', 'idempotency_key', 'reserved_ledger_entry_id', 'settled_ledger_entry_id', 'rejected_ledger_entry_id', 'occurred_at'],
                    'jsonKeys' => ['*'],
                    'stripDeep' => [],
                    'adminOnly' => false,
                ],
                'vendor' => [
                    'columns' => ['id', 'public_id', 'requested_amount_minor', 'requested_amount_currency', 'status', 'occurred_at'],
                    'jsonKeys' => [],
                    'stripDeep' => ['vendor_profile_id', 'requested_by_user_id', 'idempotency_key', 'reserved_ledger_entry_id', 'settled_ledger_entry_id', 'rejected_ledger_entry_id'],
                    'adminOnly' => false,
                ],
            ],
            timestampColumn: 'requested_at',
        );
    }

    // -------------------------------------------------------------------------
    // rank 11 — withdrawals#approved
    // -------------------------------------------------------------------------

    private static function approvedDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'withdrawals#approved',
            defaultEventKind: TimelineEventKind::Financial,
            canonicalRank: 11,
            queryBuilder: function (Withdrawal $subject, TimelineSubjectScope $scope, TimelineFilters $filters): Builder {
                $query = DB::table('withdrawals')
                    ->where('id', $subject->id)
                    ->whereNotNull('approved_at')
                    ->select([
                        'id',
                        'public_id',
                        'vendor_profile_id',
                        'requested_amount_minor',
                        'requested_amount_currency',
                        'status',
                        'approved_by_admin_id',
                        'idempotency_key',
                        'reserved_ledger_entry_id',
                        'settled_ledger_entry_id',
                        'rejected_ledger_entry_id',
                        'approved_at as occurred_at',
                    ]);

                if ($scope->getAudience() === TimelineAudience::Vendor
                    && $scope->getViewerVendorProfileId() !== null) {
                    $query->where('vendor_profile_id', $scope->getViewerVendorProfileId());
                }

                return $query;
            },
            rowMapper: function (object $row, TimelineAudience $audience): TimelineEntryDTO {
                $extra = self::baseExtra($row, $audience);

                if ($audience === TimelineAudience::Admin) {
                    $extra['approved_by_admin_id'] = $row->approved_by_admin_id ?? null;
                }

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->occurred_at)->utc(),
                    sourceTable: 'withdrawals#approved',
                    sourcePublicId: $row->public_id ?? null,
                    actorLabel: 'Admin',
                    actorRole: TimelineActorRole::Admin,
                    actionKey: 'withdrawal.approved',
                    fromState: 'pending',
                    toState: 'approved',
                    note: null,
                    eventKind: TimelineEventKind::Financial,
                    isAdminOnly: false,
                    extra: $extra,
                );
            },
            visibilityRules: [
                'admin' => [
                    'columns' => ['id', 'public_id', 'vendor_profile_id', 'requested_amount_minor', 'requested_amount_currency', 'status', 'approved_by_admin_id', 'idempotency_key', 'reserved_ledger_entry_id', 'settled_ledger_entry_id', 'rejected_ledger_entry_id', 'occurred_at'],
                    'jsonKeys' => ['*'],
                    'stripDeep' => [],
                    'adminOnly' => false,
                ],
                'vendor' => [
                    'columns' => ['id', 'public_id', 'requested_amount_minor', 'requested_amount_currency', 'status', 'occurred_at'],
                    'jsonKeys' => [],
                    'stripDeep' => ['vendor_profile_id', 'approved_by_admin_id', 'idempotency_key', 'reserved_ledger_entry_id', 'settled_ledger_entry_id', 'rejected_ledger_entry_id'],
                    'adminOnly' => false,
                ],
            ],
            timestampColumn: 'approved_at',
        );
    }

    // -------------------------------------------------------------------------
    // rank 12 — withdrawals#paid
    // -------------------------------------------------------------------------

    private static function paidDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'withdrawals#paid',
            defaultEventKind: TimelineEventKind::Financial,
            canonicalRank: 12,
            queryBuilder: function (Withdrawal $subject, TimelineSubjectScope $scope, TimelineFilters $filters): Builder {
                $query = DB::table('withdrawals')
                    ->where('id', $subject->id)
                    ->whereNotNull('paid_at')
                    ->select([
                        'id',
                        'public_id',
                        'vendor_profile_id',
                        'requested_amount_minor',
                        'requested_amount_currency',
                        'paid_amount_minor',
                        'paid_amount_currency',
                        'status',
                        'paid_by_admin_id',
                        'bank_transfer_reference',
                        'admin_payment_note',
                        'idempotency_key',
                        'reserved_ledger_entry_id',
                        'settled_ledger_entry_id',
                        'rejected_ledger_entry_id',
                        'paid_at as occurred_at',
                    ]);

                if ($scope->getAudience() === TimelineAudience::Vendor
                    && $scope->getViewerVendorProfileId() !== null) {
                    $query->where('vendor_profile_id', $scope->getViewerVendorProfileId());
                }

                return $query;
            },
            rowMapper: function (object $row, TimelineAudience $audience): TimelineEntryDTO {
                $extra = self::baseExtra($row, $audience);

                // Vendor-visible: paid amount + admin note
                $extra['paid_amount_minor'] = $row->paid_amount_minor ?? null;
                $extra['paid_amount_currency'] = $row->paid_amount_currency ?? null;

                $adminPaymentNote = is_string($row->admin_payment_note ?? null)
                    ? json_decode($row->admin_payment_note, true)
                    : ($row->admin_payment_note ?? null);

                if ($adminPaymentNote !== null) {
                    // Surface the locale-appropriate note text; view layer handles locale selection
                    $extra['admin_payment_note'] = $adminPaymentNote;
                }

                if ($audience === TimelineAudience::Admin) {
                    $extra['paid_by_admin_id'] = $row->paid_by_admin_id ?? null;
                    $extra['bank_transfer_reference'] = $row->bank_transfer_reference ?? null;
                }

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->occurred_at)->utc(),
                    sourceTable: 'withdrawals#paid',
                    sourcePublicId: $row->public_id ?? null,
                    actorLabel: 'Admin',
                    actorRole: TimelineActorRole::Admin,
                    actionKey: 'withdrawal.paid',
                    fromState: 'approved',
                    toState: 'paid',
                    note: null,
                    eventKind: TimelineEventKind::Financial,
                    isAdminOnly: false,
                    extra: $extra,
                );
            },
            visibilityRules: [
                'admin' => [
                    'columns' => ['id', 'public_id', 'vendor_profile_id', 'requested_amount_minor', 'requested_amount_currency', 'paid_amount_minor', 'paid_amount_currency', 'status', 'paid_by_admin_id', 'bank_transfer_reference', 'admin_payment_note', 'idempotency_key', 'reserved_ledger_entry_id', 'settled_ledger_entry_id', 'rejected_ledger_entry_id', 'occurred_at'],
                    'jsonKeys' => ['*'],
                    'stripDeep' => [],
                    'adminOnly' => false,
                ],
                'vendor' => [
                    'columns' => ['id', 'public_id', 'requested_amount_minor', 'requested_amount_currency', 'paid_amount_minor', 'paid_amount_currency', 'status', 'admin_payment_note', 'occurred_at'],
                    'jsonKeys' => ['admin_payment_note'],
                    'stripDeep' => ['vendor_profile_id', 'paid_by_admin_id', 'bank_transfer_reference', 'idempotency_key', 'reserved_ledger_entry_id', 'settled_ledger_entry_id', 'rejected_ledger_entry_id'],
                    'adminOnly' => false,
                ],
            ],
            timestampColumn: 'paid_at',
        );
    }

    // -------------------------------------------------------------------------
    // rank 13 — withdrawals#rejected
    // -------------------------------------------------------------------------

    private static function rejectedDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'withdrawals#rejected',
            defaultEventKind: TimelineEventKind::Financial,
            canonicalRank: 13,
            queryBuilder: function (Withdrawal $subject, TimelineSubjectScope $scope, TimelineFilters $filters): Builder {
                $query = DB::table('withdrawals')
                    ->where('id', $subject->id)
                    // rejected_at is not a dedicated column; use processed_at when status=rejected
                    ->where('status', 'rejected')
                    ->whereNotNull('processed_at')
                    ->select([
                        'id',
                        'public_id',
                        'vendor_profile_id',
                        'requested_amount_minor',
                        'requested_amount_currency',
                        'status',
                        'processed_by_user_id',
                        'rejected_reason',
                        'idempotency_key',
                        'reserved_ledger_entry_id',
                        'settled_ledger_entry_id',
                        'rejected_ledger_entry_id',
                        'processed_at as occurred_at',
                    ]);

                if ($scope->getAudience() === TimelineAudience::Vendor
                    && $scope->getViewerVendorProfileId() !== null) {
                    $query->where('vendor_profile_id', $scope->getViewerVendorProfileId());
                }

                return $query;
            },
            rowMapper: function (object $row, TimelineAudience $audience): TimelineEntryDTO {
                $extra = self::baseExtra($row, $audience);

                $rejectedReason = is_string($row->rejected_reason ?? null)
                    ? json_decode($row->rejected_reason, true)
                    : ($row->rejected_reason ?? null);

                if ($rejectedReason !== null) {
                    // Both admin and vendor see the rejection reason
                    $extra['rejected_reason'] = $rejectedReason;
                }

                if ($audience === TimelineAudience::Admin) {
                    $extra['processed_by_user_id'] = $row->processed_by_user_id ?? null;
                }

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->occurred_at)->utc(),
                    sourceTable: 'withdrawals#rejected',
                    sourcePublicId: $row->public_id ?? null,
                    actorLabel: 'Admin',
                    actorRole: TimelineActorRole::Admin,
                    actionKey: 'withdrawal.rejected',
                    fromState: 'pending',
                    toState: 'rejected',
                    note: null,
                    eventKind: TimelineEventKind::Financial,
                    isAdminOnly: false,
                    extra: $extra,
                );
            },
            visibilityRules: [
                'admin' => [
                    'columns' => ['id', 'public_id', 'vendor_profile_id', 'requested_amount_minor', 'requested_amount_currency', 'status', 'processed_by_user_id', 'rejected_reason', 'idempotency_key', 'reserved_ledger_entry_id', 'settled_ledger_entry_id', 'rejected_ledger_entry_id', 'occurred_at'],
                    'jsonKeys' => ['*'],
                    'stripDeep' => [],
                    'adminOnly' => false,
                ],
                'vendor' => [
                    'columns' => ['id', 'public_id', 'requested_amount_minor', 'requested_amount_currency', 'status', 'rejected_reason', 'occurred_at'],
                    'jsonKeys' => ['rejected_reason'],
                    'stripDeep' => ['vendor_profile_id', 'processed_by_user_id', 'idempotency_key', 'reserved_ledger_entry_id', 'settled_ledger_entry_id', 'rejected_ledger_entry_id'],
                    'adminOnly' => false,
                ],
            ],
            timestampColumn: 'processed_at',
        );
    }

    // -------------------------------------------------------------------------
    // rank 20 — wallet_ledger correlated
    // Finds ledger entries whose correlation_id matches the withdrawal's
    // settled_ledger_entry correlation_id (set at withdrawal creation time).
    // -------------------------------------------------------------------------

    private static function walletLedgerDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'wallet_ledger',
            defaultEventKind: TimelineEventKind::Financial,
            canonicalRank: 20,
            queryBuilder: function (Withdrawal $subject, TimelineSubjectScope $scope, TimelineFilters $filters): Builder {
                // Correlation strategy: correlate via the withdrawal's settled or reserved ledger entry.
                // We look up the correlation_id of the linked ledger entries, then fetch all ledger
                // entries that share that correlation_id.
                // If no ledger entry is linked, fall back to an empty result.
                $linkedEntryIds = array_filter([
                    $subject->reserved_ledger_entry_id,
                    $subject->settled_ledger_entry_id,
                    $subject->rejected_ledger_entry_id,
                ]);

                if (empty($linkedEntryIds)) {
                    // No ledger links yet — return an empty result set
                    return DB::table('wallet_ledger')->whereRaw('1 = 0');
                }

                // Resolve correlation_ids from the known entry ids
                $correlationIds = DB::table('wallet_ledger')
                    ->whereIn('id', $linkedEntryIds)
                    ->whereNotNull('correlation_id')
                    ->pluck('correlation_id')
                    ->unique()
                    ->values()
                    ->all();

                if (empty($correlationIds)) {
                    return DB::table('wallet_ledger')->whereRaw('1 = 0');
                }

                $query = DB::table('wallet_ledger')
                    ->whereIn('correlation_id', $correlationIds)
                    ->select([
                        'id',
                        'wallet_id',
                        'entry_type',
                        'direction',
                        'amount_minor',
                        'running_balance_minor',
                        'currency',
                        'transaction_group_id',
                        'correlation_id',
                        'causation_id',
                        'description_key',
                        'description_params',
                        'posted_at',
                        DB::raw('COALESCE(posted_at, created_at) as occurred_at'),
                    ])
                    ->orderBy(DB::raw('COALESCE(posted_at, created_at)'));

                // Admin-only extra columns added in select
                if ($scope->getAudience() === TimelineAudience::Admin) {
                    $query->addSelect([
                        'counter_account_type',
                        'counter_account_id',
                        'idempotency_key',
                    ]);
                }

                return $query;
            },
            rowMapper: function (object $row, TimelineAudience $audience): TimelineEntryDTO {
                $direction = $row->direction ?? null;
                $entryType = $row->entry_type ?? 'unknown';
                $actionKey = match ($entryType) {
                    'withdrawal_reserve' => 'ledger.withdrawal_reserve',
                    'withdrawal_settle' => 'ledger.withdrawal_settle',
                    'withdrawal_reject_release' => 'ledger.withdrawal_reject_release',
                    default => 'ledger.'.$entryType,
                };

                $extra = [
                    'entry_type' => $entryType,
                    'direction' => $direction,
                    'amount_minor' => $row->amount_minor,
                    'currency' => $row->currency,
                    'description_key' => $row->description_key ?? null,
                ];

                if ($audience === TimelineAudience::Admin) {
                    $extra['wallet_id'] = $row->wallet_id ?? null;
                    $extra['transaction_group_id'] = $row->transaction_group_id ?? null;
                    $extra['correlation_id'] = $row->correlation_id ?? null;
                    $extra['causation_id'] = $row->causation_id ?? null;
                    $extra['running_balance_minor'] = $row->running_balance_minor ?? null;
                    $extra['counter_account_type'] = $row->counter_account_type ?? null;
                    $extra['counter_account_id'] = $row->counter_account_id ?? null;
                    $extra['idempotency_key'] = $row->idempotency_key ?? null;
                    $extra['description_params'] = is_string($row->description_params ?? null)
                        ? json_decode($row->description_params, true)
                        : ($row->description_params ?? null);
                }

                $occurredAt = $row->occurred_at !== null
                    ? CarbonImmutable::parse($row->occurred_at)->utc()
                    : CarbonImmutable::now();

                return new TimelineEntryDTO(
                    occurredAt: $occurredAt,
                    sourceTable: 'wallet_ledger',
                    sourcePublicId: null, // wallet_ledger has no public_id
                    actorLabel: 'System',
                    actorRole: TimelineActorRole::System,
                    actionKey: $actionKey,
                    fromState: null,
                    toState: null,
                    note: null,
                    eventKind: TimelineEventKind::Financial,
                    isAdminOnly: false,
                    extra: $extra,
                );
            },
            visibilityRules: [
                'admin' => [
                    'columns' => ['id', 'wallet_id', 'entry_type', 'direction', 'amount_minor', 'running_balance_minor', 'currency', 'transaction_group_id', 'correlation_id', 'causation_id', 'counter_account_type', 'counter_account_id', 'description_key', 'description_params', 'idempotency_key', 'posted_at', 'occurred_at'],
                    'jsonKeys' => ['*'],
                    'stripDeep' => [],
                    'adminOnly' => false,
                ],
                'vendor' => [
                    'columns' => ['id', 'entry_type', 'direction', 'amount_minor', 'currency', 'description_key', 'posted_at', 'occurred_at'],
                    'jsonKeys' => [],
                    'stripDeep' => ['wallet_id', 'transaction_group_id', 'correlation_id', 'causation_id', 'running_balance_minor', 'counter_account_type', 'counter_account_id', 'idempotency_key', 'description_params'],
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
            queryBuilder: function (Withdrawal $subject, TimelineSubjectScope $scope, TimelineFilters $filters): Builder {
                return DB::table('audit_logs')
                    ->where('auditable_type', Withdrawal::class)
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
