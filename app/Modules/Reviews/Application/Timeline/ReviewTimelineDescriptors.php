<?php

declare(strict_types=1);

namespace App\Modules\Reviews\Application\Timeline;

use App\Modules\Reviews\Domain\Models\ReviewModerationLog;
use App\Modules\Reviews\Domain\Models\ServiceReview;
use App\Modules\Reviews\Domain\Models\VendorReview;
use App\Modules\Shared\Application\Timeline\DTOs\TimelineEntryDTO;
use App\Modules\Shared\Application\Timeline\DTOs\TimelineFilters;
use App\Modules\Shared\Application\Timeline\Enums\TimelineActorRole;
use App\Modules\Shared\Application\Timeline\Enums\TimelineAudience;
use App\Modules\Shared\Application\Timeline\Enums\TimelineEventKind;
use App\Modules\Shared\Application\Timeline\SourceTableDescriptor;
use App\Modules\Shared\Application\Timeline\TimelineSubjectScope;
use App\Modules\Shared\Domain\Models\AuditLog;
use Carbon\CarbonImmutable;

/**
 * Descriptor factories for review timeline subjects.
 *
 * Works for BOTH ServiceReview and VendorReview subjects: the queryBuilder
 * closures use get_class($subject) to resolve the polymorphic review_type
 * and review_id columns in review_moderation_log, and the morph columns in
 * audit_logs, so one set of descriptors covers both model classes.
 *
 * Visibility policy:
 *   Admin  — full access to all columns including internal_notes, moderator_id.
 *   Vendor — may see the final moderation decision and public reason only;
 *            internal_notes and moderator_id are stripped via stripDeep.
 *
 * Sources (by canonicalRank):
 *  10  — review_moderation_log  (Moderation)
 *  30  — audit_logs             (System)
 */
final class ReviewTimelineDescriptors
{
    /** @return list<SourceTableDescriptor> */
    public static function all(): array
    {
        return [
            self::moderationLogDescriptor(),
            self::auditLogsDescriptor(),
        ];
    }

    /**
     * Source: review_moderation_log
     *
     * Polymorphic join on (review_type, review_id).  The review_type column
     * stores the short class name used by the persisting action — we match both
     * the fully-qualified class name and the bare model name to be defensive.
     *
     * Action keys:
     *   review.approved  — to_status = 'approved'
     *   review.rejected  — to_status = 'rejected'
     *   review.hidden    — to_status = 'hidden'
     *   review.moderated — any other to_status transition
     */
    private static function moderationLogDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'review_moderation_log',
            defaultEventKind: TimelineEventKind::Moderation,
            canonicalRank: 10,

            queryBuilder: static function (
                object $subject,
                TimelineSubjectScope $scope,
                TimelineFilters $filters,
            ) {
                // The persisting action stores the FQCN as review_type.
                return ReviewModerationLog::query()
                    ->where('review_type', get_class($subject))
                    ->where('review_id', $subject->id)
                    ->orderBy('created_at');
            },

            rowMapper: static function (
                object $row,
                TimelineAudience $audience,
            ): TimelineEntryDTO {
                $toStatus = $row->to_status;

                $actionKey = match ($toStatus) {
                    'approved' => 'review.approved',
                    'rejected' => 'review.rejected',
                    'hidden' => 'review.hidden',
                    default => 'review.moderated',
                };

                $actorLabel = $row->moderator_id !== null
                    ? ($row->moderator?->name ?? 'Admin #'.$row->moderator_id)
                    : 'System';

                // Resolve a locale-appropriate public reason; fall back gracefully.
                $reason = $row->reason;
                if (is_array($reason)) {
                    $locale = app()->getLocale();
                    $note = $reason[$locale] ?? $reason['en'] ?? reset($reason) ?: null;
                } else {
                    $note = $reason ? (string) $reason : null;
                }

                // Vendor-audience extra strips moderator identity and internal notes.
                $extra = ($audience === TimelineAudience::Admin)
                    ? [
                        'from_status' => $row->from_status,
                        'to_status' => $toStatus,
                        'moderator_id' => $row->moderator_id,
                    ]
                    : [
                        'from_status' => $row->from_status,
                        'to_status' => $toStatus,
                    ];

                return new TimelineEntryDTO(
                    occurredAt: CarbonImmutable::parse($row->created_at)->utc(),
                    sourceTable: 'review_moderation_log',
                    sourcePublicId: null, // review_moderation_log has no public_id column
                    actorLabel: $actorLabel,
                    actorRole: TimelineActorRole::Admin,
                    actionKey: $actionKey,
                    fromState: $row->from_status,
                    toState: $toStatus,
                    note: $note,
                    eventKind: TimelineEventKind::Moderation,
                    isAdminOnly: false, // vendors may see final decision
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
                // Vendors see the final decision and public reason but NOT
                // internal_notes or moderator_id — those are stripped at the
                // rowMapper level and excluded here for defence-in-depth.
                'vendor' => [
                    'columns' => ['from_status', 'to_status', 'reason', 'created_at'],
                    'jsonKeys' => [],
                    'stripDeep' => ['internal_notes', 'moderator_id'],
                    'adminOnly' => false,
                ],
            ],
        );
    }

    /**
     * Source: audit_logs (polymorphic on ServiceReview / VendorReview)
     *
     * Full audit trail visible to admins; vendors are excluded — review audit
     * entries contain moderator identity and internal system metadata that must
     * not leak to the reviewed party.
     */
    private static function auditLogsDescriptor(): SourceTableDescriptor
    {
        return new SourceTableDescriptor(
            sourceTable: 'audit_logs',
            defaultEventKind: TimelineEventKind::System,
            canonicalRank: 30,

            queryBuilder: static function (
                object $subject,
                TimelineSubjectScope $scope,
                TimelineFilters $filters,
            ) {
                return AuditLog::query()
                    ->where('auditable_type', get_class($subject))
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
                // Vendor is excluded: audit entries expose moderator identity and
                // system internals that must not be visible to the reviewed party.
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
