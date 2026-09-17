<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Domain\Events;

final readonly class PromoCodeApplied
{
    public function __construct(
        public int $promoCodeId,
        public int $bookingId,
        public int $discountMinor,
        public string $currency,
    ) {}
}
