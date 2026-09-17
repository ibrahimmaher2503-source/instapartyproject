<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\Timeline;

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingItem;
use App\Modules\Booking\Domain\Models\BookingModification;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Payments\Domain\Models\Refund;
use App\Modules\Settlement\Domain\Models\WalletLedgerEntry;
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

final class BookingTimelineDescriptors
{
    /** @return array<int, SourceTableDescriptor> */
    public static function all(): array
    {
        return [
            self::stateTransitionsDescriptor(),
            self::modificationsDescriptor(),
            self::walletLedgerDescriptor(),
            self::paymentsDescriptor(),
            self::refundsDescriptor(),
            self::auditLogsDescriptor(),
            self::notificationDispatchesDescriptor(),
        ];
    }

    private static function stateTransitionsDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'state_transitions',
            defaultEventKind: TimelineEventKind::StateChange,
            canonicalRank: 10,
            queryBuilder: static function (Booking $subject, TimelineSubjectScope $scope, TimelineFilters $filters) {
                $bookingClass = Booking::class;
                $bookingVendorClass = BookingVendor::class;
                $bookingItemClass = BookingItem::class;

                // Collect vendor IDs — for vendor audience, restrict to viewer's vendor only
                $vendorQuery = $subject->vendors()->select('id', 'vendor_profile_id');
                if (
                    $scope->getAudience() === TimelineAudience::Vendor
                    && $scope->getViewerVendorProfileId() !== null
                ) {
                    $vendorQuery->where('vendor_profile_id', $scope->getViewerVendorProfileId());
                }
                $vendorIds = $vendorQuery->pluck('id')->all();

                $itemIds = [];
                if (! empty($vendorIds)) {
                    $itemIds = BookingItem::whereIn('booking_vendor_id', $vendorIds)
                        ->pluck('id')
                        ->all();
                }

                return StateTransition::where(static function ($q) use (
                    $subject,
                    $bookingClass,
                    $bookingVendorClass,
                    $bookingItemClass,
                    $vendorIds,
                    $itemIds,
                ): void {
                    // Parent booking transitions — always visible
                    $q->where(static function ($q2) use ($subject, $bookingClass): void {
                        $q2->where('transitionable_type', $bookingClass)
                            ->where('transitionable_id', $subject->id);
                    });

                    // BookingVendor transitions
                    if (! empty($vendorIds)) {
                        $q->orWhere(static function ($q2) use ($bookingVendorClass, $vendorIds): void {
                            $q2->where('transitionable_type', $bookingVendorClass)
                                ->whereIn('transitionable_id', $vendorIds);
                        });
                    }

                    // BookingItem transitions
                    if (! empty($itemIds)) {
                        $q->orWhere(static function ($q2) use ($bookingItemClass, $itemIds): void {
                            $q2->where('transitionable_type', $bookingItemClass)
                                ->whereIn('transitionable_id', $itemIds);
                        });
                    }
                })
                    ->with('triggeredByUser:id,name')
                    ->orderBy('created_at');
            },
            rowMapper: static function (StateTransition $row, TimelineAudience $audience): TimelineEntryDTO {
                $actor = $row->triggeredByUser;
                $triggerKind = $row->trigger_kind ?? 'system';

                $actorRole = match ($triggerKind) {
                    'admin' => TimelineActorRole::Admin,
                    'vendor' => TimelineActorRole::Vendor,
                    'customer' => TimelineActorRole::Customer,
                    'webhook' => TimelineActorRole::Webhook,
                    default => TimelineActorRole::System,
                };

                $actorLabel = $actor?->name ?? match ($actorRole) {
                    TimelineActorRole::Webhook => 'Payment Gateway',
                    TimelineActorRole::System => 'System',
                    TimelineActorRole::Admin => 'Admin',
                    TimelineActorRole::Vendor => 'Vendor',
                    TimelineActorRole::Customer => 'Customer',
                };

                // Build a readable action key from the morph type + transition
                $shortType = class_basename($row->transitionable_type);
                $actionKey = 'state_transition.'.strtolower($shortType).'.'.($row->to_state ?? 'unknown');

                $extra = [
                    'transitionable_type' => $row->transitionable_type,
                    'transitionable_id' => $row->transitionable_id,
                    'trigger_kind' => $triggerKind,
                    'trace_id' => $row->trace_id,
                ];

                if ($audience === TimelineAudience::Admin) {
                    $extra['context'] = $row->context;
                    $extra['reason'] = $row->reason;
                }

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->created_at),
                    sourceTable: 'state_transitions',
                    sourcePublicId: null, // state_transitions has no public_id
                    actorLabel: $actorLabel,
                    actorRole: $actorRole,
                    actionKey: $actionKey,
                    fromState: $row->from_state,
                    toState: $row->to_state,
                    note: $row->reason,
                    eventKind: TimelineEventKind::StateChange,
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
                    'columns' => ['transitionable_type', 'transitionable_id', 'from_state', 'to_state', 'trigger_kind', 'created_at'],
                    'jsonKeys' => [],
                    'stripDeep' => ['context', 'reason', 'trace_id'],
                    'adminOnly' => false,
                ],
            ],
        );
    }

    private static function modificationsDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'booking_modifications',
            defaultEventKind: TimelineEventKind::Moderation,
            canonicalRank: 15,
            queryBuilder: static function (Booking $subject, TimelineSubjectScope $scope, TimelineFilters $filters) {
                // BookingModification links to BookingVendor, not directly to Booking.
                // We must go through booking_vendors to reach the booking.
                $vendorQuery = BookingVendor::where('booking_id', $subject->id)->select('id');

                if (
                    $scope->getAudience() === TimelineAudience::Vendor
                    && $scope->getViewerVendorProfileId() !== null
                ) {
                    $vendorQuery->where('vendor_profile_id', $scope->getViewerVendorProfileId());
                }

                $vendorIds = $vendorQuery->pluck('id')->all();

                if (empty($vendorIds)) {
                    // Return an empty-result query rather than null
                    return BookingModification::whereRaw('1 = 0');
                }

                return BookingModification::whereIn('booking_vendor_id', $vendorIds)
                    ->with('proposedBy:id,name')
                    ->orderBy('created_at');
            },
            rowMapper: static function (BookingModification $row, TimelineAudience $audience): TimelineEntryDTO {
                $proposer = $row->proposedBy;

                $actorLabel = $proposer?->name ?? 'Unknown';
                $actorRole = TimelineActorRole::Vendor; // modifications are always vendor-proposed

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
                }

                if ($audience === TimelineAudience::Vendor) {
                    $extra['vendor_explanation'] = $row->vendor_explanation;
                }

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->created_at),
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
                    'stripDeep' => ['diff_snapshot'],
                    'adminOnly' => false,
                ],
            ],
        );
    }

    private static function walletLedgerDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'wallet_ledger',
            defaultEventKind: TimelineEventKind::Financial,
            canonicalRank: 20,
            queryBuilder: static function (Booking $subject, TimelineSubjectScope $scope, TimelineFilters $filters) {
                // Correlate via correlation_id on payments → wallet_ledger.correlation_id
                // Use a subquery to avoid loading all payments into PHP memory
                $paymentCorrelationIds = DB::table('payments')
                    ->where('booking_id', $subject->id)
                    ->whereNotNull('correlation_id')
                    ->pluck('correlation_id');

                if ($paymentCorrelationIds->isEmpty()) {
                    return WalletLedgerEntry::whereRaw('1 = 0');
                }

                return WalletLedgerEntry::whereIn('correlation_id', $paymentCorrelationIds)
                    ->orderBy('created_at');
            },
            rowMapper: static function (WalletLedgerEntry $row, TimelineAudience $audience): TimelineEntryDTO {
                $extra = [
                    'entry_type' => $row->entry_type?->value,
                    'direction' => $row->direction?->value,
                    'amount_minor' => $row->amount_minor,
                    'currency' => $row->currency,
                    'correlation_id' => $row->correlation_id,
                ];

                if ($audience === TimelineAudience::Admin) {
                    $extra['wallet_id'] = $row->wallet_id;
                    $extra['transaction_group_id'] = $row->transaction_group_id;
                    $extra['running_balance_minor'] = $row->running_balance_minor;
                    $extra['counter_account_type'] = $row->counter_account_type;
                    $extra['counter_account_id'] = $row->counter_account_id;
                    $extra['idempotency_key'] = $row->idempotency_key;
                }

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->created_at),
                    sourceTable: 'wallet_ledger',
                    sourcePublicId: null, // wallet_ledger has no public_id
                    actorLabel: 'System',
                    actorRole: TimelineActorRole::System,
                    actionKey: 'wallet_ledger.'.($row->entry_type?->value ?? 'unknown'),
                    fromState: null,
                    toState: null,
                    note: $row->description_key,
                    eventKind: TimelineEventKind::Financial,
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
                    'columns' => ['amount_minor', 'currency', 'direction', 'entry_type', 'created_at'],
                    'jsonKeys' => [],
                    'stripDeep' => ['wallet_id', 'transaction_group_id', 'running_balance_minor', 'counter_account_type', 'counter_account_id', 'idempotency_key', 'correlation_id'],
                    'adminOnly' => false,
                ],
            ],
        );
    }

    private static function paymentsDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'payments',
            defaultEventKind: TimelineEventKind::Financial,
            canonicalRank: 25,
            queryBuilder: static function (Booking $subject, TimelineSubjectScope $scope, TimelineFilters $filters) {
                return Payment::where('booking_id', $subject->id)
                    ->orderBy('created_at');
            },
            rowMapper: static function (Payment $row, TimelineAudience $audience): TimelineEntryDTO {
                $extra = [
                    'gateway' => $row->gateway,
                    'method' => $row->method?->value,
                    'amount_minor' => $row->amount_minor,
                    'amount_currency' => $row->amount_currency,
                    'captured_at' => $row->captured_at?->toIso8601String(),
                ];

                if ($audience === TimelineAudience::Admin) {
                    $extra['gateway_ref'] = $row->gateway_ref;
                    $extra['correlation_id'] = $row->correlation_id;
                    $extra['failure_code'] = $row->failure_code;
                    $extra['metadata'] = $row->metadata;
                }

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->created_at),
                    sourceTable: 'payments',
                    sourcePublicId: $row->public_id,
                    actorLabel: 'Payment Gateway',
                    actorRole: TimelineActorRole::Webhook,
                    actionKey: 'payment.'.($row->status !== null ? (string) $row->status : 'unknown'),
                    fromState: null,
                    toState: $row->status !== null ? (string) $row->status : null,
                    note: null,
                    eventKind: TimelineEventKind::Financial,
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
                    'columns' => ['public_id', 'amount_minor', 'amount_currency', 'method', 'captured_at', 'created_at'],
                    'jsonKeys' => [],
                    'stripDeep' => ['gateway_ref', 'correlation_id', 'failure_code', 'metadata', 'gateway'],
                    'adminOnly' => false,
                ],
            ],
        );
    }

    private static function refundsDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'refunds',
            defaultEventKind: TimelineEventKind::Financial,
            canonicalRank: 27,
            queryBuilder: static function (Booking $subject, TimelineSubjectScope $scope, TimelineFilters $filters) {
                // Refund has a direct booking_id column (confirmed by migration)
                return Refund::where('booking_id', $subject->id)
                    ->orderBy('created_at');
            },
            rowMapper: static function (Refund $row, TimelineAudience $audience): TimelineEntryDTO {
                $extra = [
                    'amount_minor' => $row->amount_minor,
                    'amount_currency' => $row->amount_currency,
                    'status' => $row->status?->value,
                    'reason_code' => $row->reason_code?->value,
                    'processed_at' => $row->processed_at?->toIso8601String(),
                ];

                if ($audience === TimelineAudience::Admin) {
                    $extra['gateway_ref'] = $row->gateway_ref;
                    $extra['initiated_by'] = $row->initiated_by;
                    $extra['ledger_group_id'] = $row->ledger_group_id;
                    $extra['idempotency_key'] = $row->idempotency_key;
                    $extra['reason_notes'] = $row->reason_notes;
                }

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->created_at),
                    sourceTable: 'refunds',
                    sourcePublicId: $row->public_id,
                    actorLabel: 'System',
                    actorRole: TimelineActorRole::System,
                    actionKey: 'refund.'.($row->status?->value ?? 'unknown'),
                    fromState: null,
                    toState: $row->status?->value,
                    note: is_array($row->reason_notes) ? ($row->reason_notes['en'] ?? null) : null,
                    eventKind: TimelineEventKind::Financial,
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
                    'columns' => ['public_id', 'amount_minor', 'amount_currency', 'reason_code', 'status', 'processed_at', 'created_at'],
                    'jsonKeys' => [],
                    'stripDeep' => ['gateway_ref', 'initiated_by', 'ledger_group_id', 'idempotency_key', 'reason_notes'],
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
            queryBuilder: static function (Booking $subject, TimelineSubjectScope $scope, TimelineFilters $filters) {
                return AuditLog::where('auditable_type', Booking::class)
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

    private static function notificationDispatchesDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'notification_dispatches',
            defaultEventKind: TimelineEventKind::System,
            canonicalRank: 50,
            queryBuilder: static function (Booking $subject, TimelineSubjectScope $scope, TimelineFilters $filters) {
                // NotificationDispatch uses reference_type/reference_id as polymorphic link
                return NotificationDispatch::where('reference_type', Booking::class)
                    ->where('reference_id', $subject->id)
                    ->orderBy('created_at');
            },
            rowMapper: static function (NotificationDispatch $row, TimelineAudience $audience): TimelineEntryDTO {
                $extra = [
                    'channel' => $row->channel?->value,
                    'status' => $row->status?->value,
                    'locale' => $row->locale,
                    'sent_at' => $row->sent_at?->toIso8601String(),
                    'delivered_at' => $row->delivered_at?->toIso8601String(),
                ];

                if ($audience === TimelineAudience::Admin) {
                    $extra['provider'] = $row->provider;
                    $extra['provider_ref'] = $row->provider_ref;
                    $extra['provider_message_id'] = $row->provider_message_id;
                    $extra['provider_status'] = $row->provider_status;
                    $extra['provider_error_code'] = $row->provider_error_code;
                    $extra['provider_error_message'] = $row->provider_error_message;
                    $extra['attempt_count'] = $row->attempt_count;
                    $extra['error_message'] = $row->error_message;
                    $extra['is_test'] = $row->is_test;
                }

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->created_at),
                    sourceTable: 'notification_dispatches',
                    sourcePublicId: $row->public_id ?? null,
                    actorLabel: 'System',
                    actorRole: TimelineActorRole::System,
                    actionKey: 'notification.'.($row->channel?->value ?? 'unknown').'.'.($row->status?->value ?? 'unknown'),
                    fromState: null,
                    toState: $row->status?->value,
                    note: null,
                    eventKind: TimelineEventKind::System,
                    isAdminOnly: true,
                    extra: $extra,
                );
            },
            visibilityRules: [
                'admin' => [
                    'columns' => ['*'],
                    'jsonKeys' => ['*'],
                    'stripDeep' => [],
                    'adminOnly' => true,
                ],
            ],
        );
    }
}
