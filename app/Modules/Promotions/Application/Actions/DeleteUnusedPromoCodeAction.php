<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Application\Actions;

use App\Modules\Promotions\Domain\Models\PromoCode;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

final class DeleteUnusedPromoCodeAction
{
    public function execute(PromoCode $promo): bool
    {
        try {
            return DB::transaction(function () use ($promo): bool {
                $locked = PromoCode::query()->lockForUpdate()->findOrFail($promo->id);
                if ($locked->uses()->exists()) {
                    return false;
                }

                return (bool) $locked->delete();
            });
        } catch (QueryException $exception) {
            // A concurrent usage insert may win after the existence check.
            if ($promo->uses()->exists()) {
                return false;
            }
            throw $exception;
        }
    }
}
