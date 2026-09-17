<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Application\Actions;

use App\Modules\Promotions\Application\DTOs\PromoValidationResultDTO;
use App\Modules\Promotions\Application\DTOs\ValidatePromoCodeDTO;
use App\Modules\Promotions\Application\Services\DiscountCalculatorService;
use App\Modules\Promotions\Domain\Enums\PromoRejectionReason;
use App\Modules\Promotions\Domain\Models\PromoCode;
use App\Modules\Promotions\Infrastructure\Repositories\EloquentPromoCodeRepository;
use Brick\Money\Money;

class ValidatePromoCodeAction
{
    public function __construct(
        private readonly EloquentPromoCodeRepository $repository,
        private readonly DiscountCalculatorService $calculator,
    ) {}

    public function execute(ValidatePromoCodeDTO $dto): PromoValidationResultDTO
    {
        $code = $this->repository->findByCode($dto->code);

        if ($code === null) {
            return PromoValidationResultDTO::invalid(
                'Promo code not found.',
                'كود الخصم غير موجود.',
                PromoRejectionReason::InvalidCode,
            );
        }

        if (! $this->isActive($code)) {
            return PromoValidationResultDTO::invalid(
                'This promo code has expired or is inactive.',
                'انتهت صلاحية كود الخصم أو أنه غير فعّال.',
                PromoRejectionReason::PromoExpired,
            );
        }

        if ($code->isExhausted()) {
            return PromoValidationResultDTO::invalid(
                'This promo code has reached its usage limit.',
                'وصل كود الخصم إلى الحد الأقصى للاستخدام.',
                PromoRejectionReason::UsageCapReached,
            );
        }

        $alreadyUsed = $code->uses()
            ->where('user_id', $dto->userId)
            ->exists();

        if ($alreadyUsed) {
            return PromoValidationResultDTO::invalid(
                'You have already used this promo code.',
                'لقد استخدمت كود الخصم هذا من قبل.',
                PromoRejectionReason::SingleUseRedeemed,
            );
        }

        $cartTotal = Money::ofMinor($dto->cartTotalMinor, $dto->cartCurrency);

        if ($code->min_order_minor !== null) {
            $minOrder = Money::ofMinor($code->min_order_minor, $code->min_order_currency);
            if ($cartTotal->isLessThan($minOrder)) {
                return PromoValidationResultDTO::invalid(
                    'Your cart total does not meet the minimum order requirement for this code.',
                    'إجمالي سلتك لا يستوفي الحد الأدنى للطلب لاستخدام هذا الكود.',
                    PromoRejectionReason::MinOrderNotMet,
                );
            }
        }

        $discount = $this->calculator->calculate($code, $cartTotal);
        $newTotal = $cartTotal->minus($discount);

        return PromoValidationResultDTO::valid($code, $discount, $newTotal);
    }

    private function isActive(PromoCode $code): bool
    {
        if (! $code->is_active) {
            return false;
        }

        $now = now();

        if ($code->starts_at !== null && $code->starts_at->isAfter($now)) {
            return false;
        }

        if ($code->expires_at !== null && $code->expires_at->isBefore($now)) {
            return false;
        }

        return true;
    }
}
