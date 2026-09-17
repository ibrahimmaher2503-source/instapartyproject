<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\VendorWishlist;
use Illuminate\Support\Facades\DB;

final class RemoveVendorFromWishlistAction
{
    public function execute(int $userId, string $vendorPublicId): void
    {
        $vendorProfileId = DB::table('vendor_profiles')
            ->where('public_id', $vendorPublicId)
            ->value('id');

        $wishlist = VendorWishlist::where('user_id', $userId)
            ->where('vendor_profile_id', $vendorProfileId)
            ->first();

        abort_if($wishlist === null, 404);

        $wishlist->delete();
    }
}
