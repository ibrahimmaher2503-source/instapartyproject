<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\PendingReviewState;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubmitServiceForReviewAction
{
    public function execute(Service $service, VendorProfile $vendorProfile): Service
    {
        if ($service->vendor_profile_id !== $vendorProfile->id) {
            throw ValidationException::withMessages(['service' => __('catalog.errors.not_owned')]);
        }

        if (! ($vendorProfile->approval_status instanceof ApprovedState)
            || ! $vendorProfile->approvedTypes()->where('product_type', $service->product_type instanceof ProductType
                ? $service->product_type->value
                : (string) $service->product_type)->exists()) {
            throw ValidationException::withMessages(['product_type' => __('catalog.errors.not_approved_for_type')]);
        }

        if (! in_array($service->status->getValue(), [ServiceStatus::Draft->value, ServiceStatus::ChangesRequested->value], true)) {
            throw ValidationException::withMessages([
                'status' => __('catalog.errors.cannot_submit'),
            ]);
        }

        return DB::transaction(function () use ($service, $vendorProfile): Service {
            return $service->status->transitionTo(PendingReviewState::class, $vendorProfile->user_id);
        });
    }
}
