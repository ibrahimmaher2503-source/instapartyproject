<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Policies\ServicePolicy;
use App\Modules\Catalog\Domain\States\ServiceStatus\DraftState;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class DeleteVendorDraftSaleServiceAction
{
    public function execute(Service $service, VendorProfile $vendorProfile): void
    {
        DB::transaction(function () use ($service, $vendorProfile): void {
            $locked = Service::query()->lockForUpdate()->findOrFail($service->getKey());

            if ($locked->vendor_profile_id !== $vendorProfile->id) {
                throw ValidationException::withMessages(['service' => __('catalog.errors.not_owned')]);
            }

            if ($locked->product_type !== ProductType::Sale) {
                throw ValidationException::withMessages(['service' => __('catalog.moderation_invalid_transition')]);
            }

            if (! ($locked->status instanceof DraftState)) {
                throw ValidationException::withMessages(['status' => __('catalog.moderation_invalid_transition')]);
            }

            $user = $vendorProfile->user;
            if ($user === null || ! app(ServicePolicy::class)->delete($user, $locked)) {
                throw new AuthorizationException(__('catalog.moderation_not_allowed'));
            }

            $locked->delete();
        }, 3);
    }
}
