<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Domain\Exceptions;

use RuntimeException;

class PromoCodeAlreadyUsedException extends RuntimeException
{
    public function __construct(
        private readonly string $messageEn = 'You have already used this promo code.',
        private readonly string $messageAr = 'لقد استخدمت كود الخصم هذا من قبل.',
    ) {
        parent::__construct($messageEn);
    }

    public function localizedMessage(string $locale): string
    {
        return $locale === 'ar' ? $this->messageAr : $this->messageEn;
    }
}
