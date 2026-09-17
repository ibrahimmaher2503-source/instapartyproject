<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\SuspendedState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class SuspendVendorAction
{
    public function execute(
        VendorProfile $vendorProfile,
        string $reason = 'manual_admin_suspension',
        ?User $actor = null,
    ): VendorProfile {
        $actor ??= auth()->user();
        abort_unless($actor?->can('suspend_vendor'), 403);

        return DB::transaction(function () use ($vendorProfile, $reason, $actor): VendorProfile {
            $locked = VendorProfile::query()->lockForUpdate()->findOrFail($vendorProfile->getKey());

            if ($locked->approval_status instanceof SuspendedState) {
                return $locked;
            }

            if (! ($locked->approval_status instanceof ApprovedState)) {
                throw new ConflictHttpException(__('identity::identity.validation.vendor_not_approved'));
            }

            $locked->approval_status->transitionTo(
                SuspendedState::class,
                (int) $actor->getKey(),
                $reason,
            );

            return $locked->refresh();
        }, 3);
    }
}
