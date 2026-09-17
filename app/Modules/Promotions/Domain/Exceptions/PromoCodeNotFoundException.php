<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Domain\Exceptions;

use RuntimeException;

class PromoCodeNotFoundException extends RuntimeException
{
    public static function forCode(string $code): self
    {
        return new self("Promo code '{$code}' not found.");
    }
}
