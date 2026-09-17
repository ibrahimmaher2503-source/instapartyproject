<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Domain\Exceptions;

use RuntimeException;

class PromoCodeScopeViolationException extends RuntimeException
{
    public function __construct(
        private readonly string $messageEn = 'This promo code is not valid for your selected services.',
        private readonly string $messageAr = 'كود الخصم هذا غير صالح للخدمات المحددة.',
    ) {
        parent::__construct($messageEn);
    }

    public function localizedMessage(string $locale): string
    {
        return $locale === 'ar' ? $this->messageAr : $this->messageEn;
    }
}
