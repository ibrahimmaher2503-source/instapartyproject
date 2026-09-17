<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Actions;

use App\Modules\Identity\Domain\Models\VendorWishlist;
use Illuminate\Support\Collection;

final class ListCustomerVendorWishlistAction
{
    /**
     * @return array{items: Collection<int, VendorWishlist>, total: int}
     */
    public function execute(int $userId, int $limit = 20): array
    {
        $query = VendorWishlist::where('user_id', $userId)
            ->with(['vendorProfile'])
            ->orderByDesc('created_at');

        $total = $query->count();
        $items = $query->limit($limit)->get();

        return ['items' => $items, 'total' => $total];
    }
}
