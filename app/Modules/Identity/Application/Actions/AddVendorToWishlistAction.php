<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\VendorWishlist;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class AddVendorToWishlistAction
{
    public function execute(int $userId, string $vendorPublicId): VendorWishlist
    {
        $vendorProfileId = DB::table('vendor_profiles')
            ->where('public_id', $vendorPublicId)
            ->value('id');

        abort_if($vendorProfileId === null, 404);

        $existing = VendorWishlist::where('user_id', $userId)
            ->where('vendor_profile_id', $vendorProfileId)
            ->first();

        if ($existing !== null) {
            abort(409, 'ALREADY_SAVED');
        }

        return DB::transaction(function () use ($userId, $vendorProfileId): VendorWishlist {
            return VendorWishlist::create([
                'public_id' => (string) Str::ulid(),
                'user_id' => $userId,
                'vendor_profile_id' => $vendorProfileId,
            ]);
        });
    }
}
