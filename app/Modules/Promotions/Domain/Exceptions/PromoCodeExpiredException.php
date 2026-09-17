<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Domain\Exceptions;

use RuntimeException;

class PromoCodeExpiredException extends RuntimeException
{
    public function __construct(
        private readonly string $messageEn = 'This promo code has expired.',
        private readonly string $messageAr = 'انتهت صلاحية كود الخصم.',
    ) {
        parent::__construct($messageEn);
    }

    public function localizedMessage(string $locale): string
    {
        return $locale === 'ar' ? $this->messageAr : $this->messageEn;
    }
}
