<?php

declare(strict_types=1);

namespace App\Modules\Communication\Application\Timeline;

use App\Modules\Communication\Domain\Models\ChatMessageLog;
use App\Modules\Communication\Domain\Models\ChatModerationFlag;
use App\Modules\Communication\Domain\Models\ChatThread;
use App\Modules\Communication\Domain\Models\NotificationDispatch;
use App\Modules\Shared\Application\Timeline\DTOs\TimelineEntryDTO;
use App\Modules\Shared\Application\Timeline\DTOs\TimelineFilters;
use App\Modules\Shared\Application\Timeline\Enums\TimelineActorRole;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Application\Timeline\Enums\TimelineEventKind;
use App\Modules\Shared\Application\Timeline\SourceTableDescriptor;
use App\Modules\Shared\Application\Timeline\TimelineSubjectScope;
use App\Modules\Shared\Domain\Models\AuditLog;
use BackedEnum;
use Carbon\CarbonImmutable;

/**
 * Descriptor factories for the ChatThread timeline subject.
 *
 * ADMIN-ONLY subject: ChatThread is never visible to vendors or customers via
 * the timeline pipeline. Every descriptor marks vendor 'adminOnly' => true.
 *
 * Sources (by canonicalRank):
 *  10  — chat_moderation_flags   (Moderation)
 *  15  — chat_message_log        (System — system-kind messages only)
 *  30  — audit_logs              (System)
 *  50  — notification_dispatches (System)
 */
final class ChatThreadTimelineDescriptors
{
    /** @return list<SourceTableDescriptor> */
    public static function all(): array
    {
        return [
            self::moderationFlagsDescriptor(),
            self::systemMessagesDescriptor(),
            self::auditLogsDescriptor(),
            self::notificationDispatchesDescriptor(),
        ];
    }

    /**
     * Source: chat_moderation_flags
     *
     * Flags are joined through chat_message_log → chat_thread_id so we can
     * filter by the thread subject.  Each flag row maps to either
     * `chat.flag_raised` (not yet reviewed) or `chat.flag_resolved` (reviewed).
     */
    private static function moderationFlagsDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'chat_moderation_flags',
            defaultEventKind: TimelineEventKind::Moderation,
            canonicalRank: 10,

            queryBuilder: static function (
                ChatThread $subject,
                TimelineSubjectScope $scope,
                TimelineFilters $filters,
            ) {
                // Flags belong to ChatMessageLog rows which belong to the thread.
                return ChatModerationFlag::query()
                    ->select([
                        'chat_moderation_flags.*',
                        'chat_message_log.chat_thread_id',
                    ])
                    ->join(
                        'chat_message_log',
                        'chat_message_log.id',
                        '=',
                        'chat_moderation_flags.chat_message_log_id',
                    )
                    ->where('chat_message_log.chat_thread_id', $subject->id)
                    ->orderBy('chat_moderation_flags.created_at');
            },

            rowMapper: static function (
                object $row,
                TimelineAudience $audience,
            ): TimelineEntryDTO {
                $isResolved = $row->reviewed_at !== null;
                $actionKey = $isResolved ? 'chat.flag_resolved' : 'chat.flag_raised';
                $occurredAt = CarbonImmutable::parse($isResolved ? $row->reviewed_at : $row->created_at)
                    ->utc();

                // Actor is the reviewer when resolved, otherwise System (auto-detection).
                if ($isResolved && $row->reviewed_by !== null) {
                    $actorRole = TimelineActorRole::Admin;
                    $actorLabel = 'Admin #'.$row->reviewed_by;
                } else {
                    $actorRole = TimelineActorRole::System;
                    $actorLabel = 'System';
                }

                return new TimelineEntryDTO(
                    occurredAt: $occurredAt,
                    sourceTable: 'chat_moderation_flags',
                    sourcePublicId: $row->public_id ?? null,
                    actorLabel: $actorLabel,
                    actorRole: $actorRole,
                    actionKey: $actionKey,
                    fromState: null,
                    toState: $isResolved ? 'resolved' : 'open',
                    note: $row->matched_pattern
                        ? 'Pattern: '.$row->matched_pattern
                        : null,
                    eventKind: TimelineEventKind::Moderation,
                    isAdminOnly: true,
                    extra: [
                        'flag_type' => $row->flag_type instanceof BackedEnum
                            ? $row->flag_type->value
                            : $row->flag_type,
                        'action_taken' => $row->action_taken instanceof BackedEnum
                            ? $row->action_taken->value
                            : $row->action_taken,
                        'reviewed_by' => $row->reviewed_by,
                        'reviewed_at' => $row->reviewed_at?->toIso8601String(),
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
                // Vendors never see ChatThread timeline entries.
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
     * Source: chat_message_log (system messages only)
     *
     * Only rows with message_kind = 'system' are surfaced.  These correspond to
     * automated events such as thread freeze / unfreeze.  The action_key is
     * inferred from the flag_reason column that the freeze/unfreeze actions stamp
     * onto the synthetic system message row.
     */
    private static function systemMessagesDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'chat_message_log',
            defaultEventKind: TimelineEventKind::System,
            canonicalRank: 15,

            queryBuilder: static function (
                ChatThread $subject,
                TimelineSubjectScope $scope,
                TimelineFilters $filters,
            ) {
                return ChatMessageLog::query()
                    ->where('chat_thread_id', $subject->id)
                    ->where('message_kind', 'system')
                    ->orderBy('created_at');
            },

            rowMapper: static function (
                object $row,
                TimelineAudience $audience,
            ): TimelineEntryDTO {
                // flag_reason carries the semantic intent for system messages
                // ('frozen', 'unfrozen', etc.).
                $flagReason = $row->flag_reason ?? '';
                $actionKey = match (true) {
                    str_contains($flagReason, 'unfreeze'),
                    str_contains($flagReason, 'unfrozen') => 'chat.unfrozen',
                    str_contains($flagReason, 'freeze'),
                    str_contains($flagReason, 'frozen') => 'chat.frozen',
                    default => 'chat.system_message',
                };

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->created_at)->utc(),
                    sourceTable: 'chat_message_log',
                    sourcePublicId: $row->public_id ?? null,
                    actorLabel: 'System',
                    actorRole: TimelineActorRole::System,
                    actionKey: $actionKey,
                    fromState: null,
                    toState: null,
                    note: $flagReason ?: null,
                    eventKind: TimelineEventKind::System,
                    isAdminOnly: true,
                    extra: [
                        'message_kind' => $row->message_kind,
                        'sender_id' => $row->sender_id,
                        'detected_locale' => $row->detected_locale,
                        'redacted' => (bool) $row->redacted,
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
                    'columns' => [],
                    'jsonKeys' => [],
                    'stripDeep' => [],
                    'adminOnly' => true,
                ],
            ],
        );
    }

    /**
     * Source: audit_logs (polymorphic on ChatThread)
     *
     * Only admins interact with ChatThread through the admin panel, so all
     * audit_log rows for this subject are admin-only by definition.
     */
    private static function auditLogsDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'audit_logs',
            defaultEventKind: TimelineEventKind::System,
            canonicalRank: 30,

            queryBuilder: static function (
                ChatThread $subject,
                TimelineSubjectScope $scope,
                TimelineFilters $filters,
            ) {
                return AuditLog::query()
                    ->where('auditable_type', ChatThread::class)
                    ->where('auditable_id', $subject->id)
                    ->orderBy('created_at');
            },

            rowMapper: static function (
                object $row,
                TimelineAudience $audience,
            ): TimelineEntryDTO {
                $actorRole = match (true) {
                    $row->user_id !== null => TimelineActorRole::Admin,
                    default => TimelineActorRole::System,
                };

                $actorLabel = $row->user_id !== null
                    ? ($row->actor->name ?? 'Admin #'.$row->user_id)
                    : 'System';

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->created_at)->utc(),
                    sourceTable: 'audit_logs',
                    sourcePublicId: null,
                    actorLabel: $actorLabel,
                    actorRole: $actorRole,
                    actionKey: $row->action ?? 'audit.entry',
                    fromState: null,
                    toState: null,
                    note: null,
                    eventKind: TimelineEventKind::System,
                    isAdminOnly: true,
                    extra: [
                        'changes' => $row->changes ?? [],
                        'user_id' => $row->user_id,
                        'ip' => $row->ip_address ?? null,
                        'user_agent' => $row->user_agent ?? null,
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
                    'columns' => [],
                    'jsonKeys' => [],
                    'stripDeep' => [],
                    'adminOnly' => true,
                ],
            ],
        );
    }

    /**
     * Source: notification_dispatches (polymorphic reference to ChatThread)
     *
     * The `notification_dispatches` table links to a subject via `reference_type`
     * and `reference_id` (morph columns).  Only admins view this source.
     */
    private static function notificationDispatchesDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'notification_dispatches',
            defaultEventKind: TimelineEventKind::System,
            canonicalRank: 50,

            queryBuilder: static function (
                ChatThread $subject,
                TimelineSubjectScope $scope,
                TimelineFilters $filters,
            ) {
                return NotificationDispatch::query()
                    ->where('reference_type', ChatThread::class)
                    ->where('reference_id', $subject->id)
                    ->orderBy('created_at');
            },

            rowMapper: static function (
                object $row,
                TimelineAudience $audience,
            ): TimelineEntryDTO {
                $status = $row->status instanceof BackedEnum
                    ? $row->status->value
                    : (string) $row->status;

                $channel = $row->channel instanceof BackedEnum
                    ? $row->channel->value
                    : (string) $row->channel;

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->created_at)->utc(),
                    sourceTable: 'notification_dispatches',
                    sourcePublicId: $row->public_id ?? null,
                    actorLabel: 'System',
                    actorRole: TimelineActorRole::System,
                    actionKey: 'notification.dispatched',
                    fromState: null,
                    toState: $status,
                    note: null,
                    eventKind: TimelineEventKind::System,
                    isAdminOnly: true,
                    extra: [
                        'channel' => $channel,
                        'status' => $status,
                        'locale' => $row->locale,
                        'user_id' => $row->user_id,
                        'provider' => $row->provider,
                        'provider_ref' => $row->provider_ref,
                        'attempt_count' => $row->attempt_count,
                        'sent_at' => $row->sent_at?->toIso8601String(),
                        'delivered_at' => $row->delivered_at?->toIso8601String(),
                        'error_message' => $row->error_message,
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
                    'columns' => [],
                    'jsonKeys' => [],
                    'stripDeep' => [],
                    'adminOnly' => true,
                ],
            ],
        );
    }
}
