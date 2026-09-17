<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Application\Services\VendorApprovalEligibilityService;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\PendingState;
use App\Modules\Shared\Domain\Enums\ChangeRequestStatus;
use App\Modules\Shared\Domain\Models\ChangeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

final class ApproveVendorProfileAction
{
    public function __construct(
        private readonly VendorApprovalEligibilityService $eligibility,
    ) {}

    /**
     * @throws ValidationException
     */
    public function execute(VendorProfile $vendorProfile, ?User $actor = null): VendorProfile
    {
        $actor ??= auth()->user();

        abort_unless($actor?->can('approve_vendor_profile'), 403);

        return DB::transaction(function () use ($vendorProfile, $actor): VendorProfile {
            $locked = VendorProfile::query()
                ->with('user')
                ->lockForUpdate()
                ->findOrFail($vendorProfile->getKey());

            if ($locked->approval_status instanceof ApprovedState) {
                return $locked;
            }

            if (! ($locked->approval_status instanceof PendingState)) {
                throw new UnprocessableEntityHttpException(
                    __('identity::identity.approval_eligibility.pending_only')
                );
            }

            $result = $this->eligibility->evaluate($locked);

            if (! $result->eligible) {
                throw ValidationException::withMessages([
                    'eligibility' => array_values($result->errors),
                ]);
            }

            $locked->approval_status->transitionTo(ApprovedState::class, (int) $actor->getKey());

            ChangeRequest::query()
                ->where('subject_type', 'vendor_profile')
                ->where('subject_id', $locked->getKey())
                ->where('status', ChangeRequestStatus::Resubmitted->value)
                ->lockForUpdate()
                ->update([
                    'status' => ChangeRequestStatus::Resolved->value,
                    'resolved_by_admin_id' => $actor->getKey(),
                    'resolved_at' => now(),
                ]);

            return $locked->refresh();
        }, 3);
    }
}
