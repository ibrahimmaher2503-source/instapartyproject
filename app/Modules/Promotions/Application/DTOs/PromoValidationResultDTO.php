<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Application\DTOs;

use App\Modules\Promotions\Domain\Enums\PromoRejectionReason;
use App\Modules\Promotions\Domain\Models\PromoCode;
use Brick\Money\Money;

final readonly class PromoValidationResultDTO
{
    public function __construct(
        public bool $valid,
        public ?PromoCode $promoCode,
        public ?Money $discount,
        public ?Money $newTotal,
        public ?string $errorMessageEn,
        public ?string $errorMessageAr,
        public ?PromoRejectionReason $rejectionReason = null,
    ) {}

    public static function valid(PromoCode $code, Money $discount, Money $newTotal): self
    {
        return new self(
            valid: true,
            promoCode: $code,
            discount: $discount,
            newTotal: $newTotal,
            errorMessageEn: null,
            errorMessageAr: null,
        );
    }

    public static function invalid(string $messageEn, string $messageAr, ?PromoRejectionReason $rejectionReason = null): self
    {
        return new self(
            valid: false,
            promoCode: null,
            discount: null,
            newTotal: null,
            errorMessageEn: $messageEn,
            errorMessageAr: $messageAr,
            rejectionReason: $rejectionReason,
        );
    }

    public function errorMessage(string $locale): ?string
    {
        if ($this->valid) {
            return null;
        }

        return $locale === 'ar' ? $this->errorMessageAr : $this->errorMessageEn;
    }
}
