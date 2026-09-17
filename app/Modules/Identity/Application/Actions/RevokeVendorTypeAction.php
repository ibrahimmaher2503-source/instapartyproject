<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Events\VendorTypeRevoked;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class RevokeVendorTypeAction
{
    public function execute(
        VendorProfile $vendorProfile,
        ProductType $productType,
        array $revokeReason = [],
        ?User $actor = null,
        bool $system = false,
    ): VendorApprovedProductType {
        $actor ??= auth()->user();
        abort_unless($system || $actor?->can('revoke_vendor_type'), 403);
        $revokeReason = array_filter($revokeReason, fn ($value): bool => filled($value));

        if (! $system && $revokeReason === []) {
            throw ValidationException::withMessages([
                'revoke_reason' => [__('identity::identity.validation.revoke_reason_required')],
            ]);
        }

        return DB::transaction(function () use ($vendorProfile, $productType, $revokeReason, $actor): VendorApprovedProductType {
            $lockedProfile = VendorProfile::query()->lockForUpdate()->findOrFail($vendorProfile->getKey());
            $row = VendorApprovedProductType::query()
                ->where('vendor_profile_id', $lockedProfile->id)
                ->where('product_type', $productType)
                ->latest('id')
                ->lockForUpdate()
                ->first();

            if ($row === null) {
                throw new ConflictHttpException(__('identity::identity.validation.type_approval_not_found'));
            }

            if ($row->revoked_at !== null) {
                return $row;
            }

            $row->update([
                'revoked_at' => now(),
                'revoked_by' => $actor?->getKey(),
                'revoke_reason' => $revokeReason,
            ]);

            $permissions = $this->permissions($productType);
            User::query()->findOrFail($lockedProfile->user_id)->revokePermissionTo($permissions);

            activity()
                ->on($lockedProfile)
                ->causedBy($actor)
                ->withProperties([
                    'product_type' => $productType->value,
                    'permissions_revoked' => $permissions,
                    'reason' => $revokeReason,
                ])
                ->log('revoked_vendor_type');

            DB::afterCommit(fn () => event(new VendorTypeRevoked(
                vendorProfileId: $lockedProfile->id,
                productType: $productType,
                revokedBy: $actor ? (int) $actor->getKey() : null,
                reason: $revokeReason,
            )));

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
