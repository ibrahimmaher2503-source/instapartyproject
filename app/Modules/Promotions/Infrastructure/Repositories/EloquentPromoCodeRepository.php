<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Infrastructure\Repositories;

use App\Modules\Promotions\Domain\Models\PromoCode;
use Illuminate\Support\Facades\DB;

class EloquentPromoCodeRepository
{
    public function findByCode(string $code): ?PromoCode
    {
        return PromoCode::where('code', $code)->first();
    }

    /**
     * Atomically increments used_count only if under limit.
     * Returns true on success, false if the code is exhausted.
     */
    public function incrementUsedCount(int $id, ?int $maxUses): bool
    {
        $affected = DB::table('promo_codes')
            ->where('id', $id)
            ->where(function ($query) use ($maxUses) {
                if ($maxUses === null) {
                    return;
                }
                $query->whereColumn('used_count', '<', DB::raw((string) $maxUses));
            })
            ->update(['used_count' => DB::raw('used_count + 1')]);

        return $affected > 0;
    }
}
