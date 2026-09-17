<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain\States\VendorApprovalStatus;

use App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions\ApproveVendorTransition;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions\RejectVendorTransition;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions\RequestVendorChangesTransition;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions\ResetVendorToPendingTransition;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions\SuspendVendorTransition;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions\UnsuspendVendorTransition;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\Transitions\VendorResubmitTransition;
use App\Modules\Shared\Domain\Contracts\TranslatableState;
use Spatie\ModelStates\State;
use Spatie\ModelStates\StateConfig;

abstract class VendorApprovalState extends State implements TranslatableState
{
    public static function label(string $stateName, string $locale): ?string
    {
        $key = "identity::identity.status.{$stateName}";
        $translated = __($key, [], $locale);

        if ($translated !== $key) {
            return $translated;
        }

        // Hard-coded fallback for all known vendor approval state names
        return match ($stateName) {
            'pending' => $locale === 'ar' ? 'قيد المراجعة' : 'Pending Review',
            'approved' => $locale === 'ar' ? 'موافق عليه' : 'Approved',
            'rejected' => $locale === 'ar' ? 'مرفوض' : 'Rejected',
            'suspended' => $locale === 'ar' ? 'موقوف' : 'Suspended',
            'changes_requested' => $locale === 'ar' ? 'مطلوب تعديلات' : 'Changes Requested',
            default => null,
        };
    }

    public function isActionable(): bool
    {
        return $this instanceof PendingState
            || $this instanceof ChangesRequestedState;
    }

    public static function config(): StateConfig
    {
        return parent::config()
            ->default(PendingState::class)
            ->allowTransition(PendingState::class, ApprovedState::class, ApproveVendorTransition::class)
            ->allowTransition(PendingState::class, RejectedState::class, RejectVendorTransition::class)
            ->allowTransition(PendingState::class, ChangesRequestedState::class, RequestVendorChangesTransition::class)
            ->allowTransition(ChangesRequestedState::class, PendingState::class, VendorResubmitTransition::class)
            ->allowTransition(ApprovedState::class, SuspendedState::class, SuspendVendorTransition::class)
            ->allowTransition(SuspendedState::class, ApprovedState::class, UnsuspendVendorTransition::class)
            ->allowTransition(RejectedState::class, PendingState::class, ResetVendorToPendingTransition::class);
    }
}
