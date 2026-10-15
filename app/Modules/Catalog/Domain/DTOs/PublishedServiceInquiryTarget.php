<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\DTOs;

final readonly class PublishedServiceInquiryTarget
{
    public function __construct(
        public int $serviceId,
        public string $servicePublicId,
        public int $vendorProfileId,
        public string $serviceNameEn,
        public string $serviceNameAr,
        public string $vendorNameEn,
        public string $vendorNameAr,
    ) {}
}
