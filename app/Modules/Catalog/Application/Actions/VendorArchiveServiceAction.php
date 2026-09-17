<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\ArchivedState;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VendorArchiveServiceAction
{
    public function execute(Service $service, VendorProfile $vendorProfile): void
    {
        if ($service->vendor_profile_id !== $vendorProfile->id) {
            throw ValidationException::withMessages(['service' => __('catalog.errors.not_owned')]);
        }

        // canTransitionTo() instantiates the transition class, so it needs the
        // same actor-id arg as transitionTo() — without it, spatie constructs
        // ArchiveServiceTransition with the model only and fatals.
        if (! $service->status->canTransitionTo(ArchivedState::class, $vendorProfile->user_id)) {
            throw ValidationException::withMessages(['status' => __('catalog.moderation_invalid_transition')]);
        }

        // Drive the state machine — a raw status update bypassed it and fataled
        // inside the state cast (ArchiveServiceTransition needs the actor id).
        // Found by VendorArchiveServiceEndpointTest, 2026-06-06.
        DB::transaction(fn () => $service->status->transitionTo(ArchivedState::class, $vendorProfile->user_id));
    }
}
