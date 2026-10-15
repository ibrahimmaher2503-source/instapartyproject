<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Contracts;

use App\Modules\Catalog\Domain\DTOs\PublishedServiceInquiryTarget;

interface PublishedServiceInquiryTargetReader
{
    public function find(string $servicePublicId): ?PublishedServiceInquiryTarget;
}
