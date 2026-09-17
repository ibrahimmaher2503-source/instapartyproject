<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\PendingState;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\RejectedState;
use App\Modules\Shared\Domain\Enums\ChangeRequestStatus;
use App\Modules\Shared\Domain\Models\ChangeRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class RejectVendorProfileAction
{
    public function execute(VendorProfile $vendorProfile, array $rejectionReason, ?User $actor = null): VendorProfile
    {
        $actor ??= auth()->user();
        abort_unless($actor?->can('reject_vendor_profile'), 403);

        $rejectionReason = array_filter($rejectionReason, fn ($value): bool => filled($value));
        if ($rejectionReason === []) {
            throw ValidationException::withMessages([
                'rejection_reason' => __('identity::identity.validation.rejection_reason_required'),
            ]);
        }

        return DB::transaction(function () use ($vendorProfile, $rejectionReason, $actor): VendorProfile {
            $locked = VendorProfile::query()->lockForUpdate()->findOrFail($vendorProfile->getKey());

            if ($locked->approval_status instanceof RejectedState && $locked->rejection_reason === $rejectionReason) {
                return $locked;
            }

            if (! ($locked->approval_status instanceof PendingState)) {
                throw new ConflictHttpException(__('identity::identity.approval_eligibility.pending_only'));
            }

            $locked->approval_status->transitionTo(
                RejectedState::class,
                (int) $actor->getKey(),
                $rejectionReason,
            );

            ChangeRequest::query()
                ->where('subject_type', 'vendor_profile')
                ->where('subject_id', $locked->getKey())
                ->where('status', ChangeRequestStatus::Resubmitted->value)
                ->lockForUpdate()
                ->update([
                    'status' => ChangeRequestStatus::EscalatedToRejection->value,
                    'resolution_notes' => json_encode($rejectionReason, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'resolved_by_admin_id' => $actor->getKey(),
                    'resolved_at' => now(),
                ]);

            return $locked->refresh();
        }, 3);
    }
}
