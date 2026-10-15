<?php

declare(strict_types=1);

namespace App\Modules\Booking\Application\DTOs;

final readonly class ServiceThemeReadDTO
{
    public function __construct(
        public string $publicId,
        public string $code,
        public string $nameEn,
        public string $nameAr,
    ) {}

    /** @return array<string, mixed> */
    public function snapshot(): array
    {
        return [
            'public_id' => $this->publicId,
            'code' => $this->code,
            'name' => ['en' => $this->nameEn, 'ar' => $this->nameAr],
        ];
    }
}
