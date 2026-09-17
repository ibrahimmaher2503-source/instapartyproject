<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Domain\Exceptions;

use RuntimeException;

class PromoCodeExhaustedException extends RuntimeException
{
    public function __construct(
        private readonly string $messageEn = 'This promo code has reached its usage limit.',
        private readonly string $messageAr = 'وصل كود الخصم إلى الحد الأقصى للاستخدام.',
    ) {
        parent::__construct($messageEn);
    }

    public function localizedMessage(string $locale): string
    {
        return $locale === 'ar' ? $this->messageAr : $this->messageEn;
    }
}
