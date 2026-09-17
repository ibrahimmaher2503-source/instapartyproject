<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Application\Services;

use App\Modules\Promotions\Domain\Enums\PromoCodeType;
use App\Modules\Promotions\Domain\Models\PromoCode;
use Brick\Math\RoundingMode;
use Brick\Money\Money;

class DiscountCalculatorService
{
    public function calculate(PromoCode $code, Money $cartTotal): Money
    {
        $discount = match ($code->type) {
            PromoCodeType::Percentage => $this->calculatePercentage($code, $cartTotal),
            PromoCodeType::Fixed => $this->calculateFixed($code, $cartTotal),
        };

        // Never discount more than the cart total
        if ($discount->isGreaterThan($cartTotal)) {
            return $cartTotal;
        }

        return $discount;
    }

    private function calculatePercentage(PromoCode $code, Money $cartTotal): Money
    {
        $percent = $code->discount_percent ?? 0;
        $discount = $cartTotal->multipliedBy($percent / 100, RoundingMode::HALF_UP);

        // Apply cap if set
        if ($code->max_discount_minor !== null) {
            $cap = Money::ofMinor($code->max_discount_minor, $code->max_discount_currency);
            if ($discount->isGreaterThan($cap)) {
                return $cap;
            }
        }

        return $discount;
    }

    private function calculateFixed(PromoCode $code, Money $cartTotal): Money
    {
        return Money::ofMinor($code->discount_minor ?? 0, $code->discount_currency);
    }
}
