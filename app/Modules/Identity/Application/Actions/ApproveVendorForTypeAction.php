<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Events\VendorApprovedForType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ApproveVendorForTypeAction
{
    public function execute(
        VendorProfile $vendorProfile,
        ProductType $productType,
        ?User $actor = null,
    ): VendorApprovedProductType {
        $actor ??= auth()->user();
        abort_unless($actor?->can('approve_vendor_for_type'), 403);

        return DB::transaction(function () use ($vendorProfile, $productType, $actor): VendorApprovedProductType {
            $lockedProfile = VendorProfile::query()->lockForUpdate()->findOrFail($vendorProfile->getKey());

            if (! ($lockedProfile->approval_status instanceof ApprovedState)) {
                throw new ConflictHttpException(__('identity::identity.validation.vendor_not_approved'));
            }

            $existing = VendorApprovedProductType::query()
                ->where('vendor_profile_id', $lockedProfile->id)
                ->where('product_type', $productType)
                ->active()
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $row = VendorApprovedProductType::query()->create([
                'vendor_profile_id' => $lockedProfile->id,
                'product_type' => $productType,
                'approved_at' => now(),
                'approved_by' => $actor->getKey(),
            ]);

            $permissions = $this->permissions($productType);
            User::query()->findOrFail($lockedProfile->user_id)->givePermissionTo($permissions);

            activity()
                ->on($lockedProfile)
                ->causedBy($actor)
                ->withProperties(['product_type' => $productType->value, 'permissions_granted' => $permissions])
                ->log('approved_vendor_for_type');

            DB::afterCommit(fn () => event(new VendorApprovedForType($row)));

            return $row;
        }, 3);
    }

    private function permissions(ProductType $productType): array
    {
        return [
            "service.create.{$productType->value}.own",
            "service.update.{$productType->value}.own",
            "service.delete.{$productType->value}.own",
            "service.publish.{$productType->value}.own",
        ];
    }
}
