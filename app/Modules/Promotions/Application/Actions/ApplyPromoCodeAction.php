<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Application\Actions;

use App\Modules\Promotions\Domain\Events\PromoCodeApplied;
use App\Modules\Promotions\Domain\Exceptions\PromoCodeExhaustedException;
use App\Modules\Promotions\Domain\Models\PromoCode;
use App\Modules\Promotions\Domain\Models\PromoCodeUse;
use App\Modules\Promotions\Infrastructure\Repositories\EloquentPromoCodeRepository;
use Brick\Money\Money;
use Illuminate\Support\Facades\DB;

class ApplyPromoCodeAction
{
    public function __construct(
        private readonly EloquentPromoCodeRepository $repository,
    ) {}

    public function execute(string $code, int $bookingId, int $userId, Money $discount): PromoCodeUse
    {
        $promoCode = PromoCode::where('code', $code)->firstOrFail();

        $success = $this->repository->incrementUsedCount($promoCode->id, $promoCode->max_uses);

        if (! $success) {
            throw new PromoCodeExhaustedException;
        }

        $use = PromoCodeUse::create([
            'promo_code_id' => $promoCode->id,
            'user_id' => $userId,
            'booking_id' => $bookingId,
            'discount_minor' => $discount->getMinorAmount()->toInt(),
            'discount_currency' => $discount->getCurrency()->getCurrencyCode(),
            'used_at' => now(),
        ]);

        DB::afterCommit(fn () => event(new PromoCodeApplied(
            promoCodeId: $promoCode->id,
            bookingId: $bookingId,
            discountMinor: $discount->getMinorAmount()->toInt(),
            currency: $discount->getCurrency()->getCurrencyCode(),
        )));

        return $use;
    }
}
