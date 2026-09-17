<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Application\DTOs;

final readonly class ValidatePromoCodeDTO
{
    public function __construct(
        public string $code,
        public string $bookingDraftPublicId,
        public int $cartTotalMinor,
        public string $cartCurrency,
        public int $userId,
    ) {}
}
