<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\DTOs;

final readonly class VendorProfileChangeRequestItemData
{
    public function __construct(
        public string $publicId,
        public string $fieldPath,
        public ?string $requestedChangeEn,
        public ?string $requestedChangeAr,
    ) {}
}
