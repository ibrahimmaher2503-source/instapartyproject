<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\States\ServiceStatus;

use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\ApproveServiceTransition;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\ArchiveServiceTransition;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\RejectServiceTransition;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\RequestServiceChangesTransition;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\ResubmitAfterChangesTransition;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\SubmitForReviewTransition;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\UnarchiveServiceTransition;
use App\Modules\Shared\Domain\Contracts\TranslatableState;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class ServiceState extends State implements TranslatableState
{
    public static function label(string $stateName, string $locale): ?string
    {
        $key = "catalog::catalog.status.{$stateName}";
        $translated = __($key, [], $locale);

        if ($translated !== $key) {
            return $translated;
        }

        // Hard-coded fallback for all known service status state names
        return match ($stateName) {
            'draft' => $locale === 'ar' ? 'مسودة' : 'Draft',
            'pending_review' => $locale === 'ar' ? 'قيد المراجعة' : 'Pending Review',
            'changes_requested' => $locale === 'ar' ? 'مطلوب تعديلات' : 'Changes Requested',
            'published' => $locale === 'ar' ? 'منشور' : 'Published',
            'rejected' => $locale === 'ar' ? 'مرفوض' : 'Rejected',
            'archived' => $locale === 'ar' ? 'مؤرشف' : 'Archived',
            default => null,
        };
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(DraftState::class)
            ->allowTransition(DraftState::class, PendingReviewState::class, SubmitForReviewTransition::class)
            ->allowTransition(PendingReviewState::class, PublishedState::class, ApproveServiceTransition::class)
            ->allowTransition(PendingReviewState::class, RejectedState::class, RejectServiceTransition::class)
            ->allowTransition(PendingReviewState::class, ChangesRequestedState::class, RequestServiceChangesTransition::class)
            ->allowTransition(PendingReviewState::class, DraftState::class)
            ->allowTransition(ChangesRequestedState::class, PendingReviewState::class, ResubmitAfterChangesTransition::class)
            ->allowTransition(ChangesRequestedState::class, DraftState::class)
            ->allowTransition(PublishedState::class, PendingReviewState::class)
            ->allowTransition(PublishedState::class, ArchivedState::class, ArchiveServiceTransition::class)
            ->allowTransition(PublishedState::class, DraftState::class)
            ->allowTransition(RejectedState::class, ArchivedState::class)
            ->allowTransition(ArchivedState::class, DraftState::class, UnarchiveServiceTransition::class);
    }
}
