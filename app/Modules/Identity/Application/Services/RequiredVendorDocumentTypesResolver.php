<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Domain\Enums\BusinessType;

final class RequiredVendorDocumentTypesResolver
{
    /** @return array<int, string> */
    public function forBusinessType(BusinessType|string|null $businessType): array
    {
        $type = $businessType instanceof BusinessType
            ? $businessType
            : (BusinessType::tryFrom((string) $businessType) ?? BusinessType::Individual);

        return match ($type) {
            BusinessType::Individual => ['national_id', 'iban_proof'],
            BusinessType::Company => ['cr', 'tax_card', 'iban_proof'],
            BusinessType::Establishment => ['cr', 'tax_card', 'national_id', 'iban_proof'],
        };
    }
}
