<?php

declare(strict_types=1);

namespace App\Modules\Identity\Application\Services;

use App\Modules\Identity\Domain\Enums\BusinessType;
use App\Modules\Identity\Domain\Models\VendorDocumentType;

final class RequiredVendorDocumentTypesResolver
{
    /** @var array<string, array<int, string>> */
    private array $requiredTypes = [];

    /** @return array<int, string> */
    public function forBusinessType(BusinessType|string|null $businessType): array
    {
        $type = $businessType instanceof BusinessType
            ? $businessType
            : (BusinessType::tryFrom((string) $businessType) ?? BusinessType::Individual);

        return $this->requiredTypes[$type->value] ??= VendorDocumentType::query()->where('is_active', true)->orderBy('id')->get()
            ->filter(fn (VendorDocumentType $documentType): bool => in_array($type->value, $documentType->required_for, true))
            ->pluck('key')->all();
    }

    public function forgetRequiredTypes(): void
    {
        $this->requiredTypes = [];
    }

    /** @return array<string, string> */
    public function options(): array
    {
        return VendorDocumentType::query()->where('is_active', true)->orderBy('id')->get()
            ->mapWithKeys(fn (VendorDocumentType $type): array => [$type->key => $type->getTranslation('name', app()->getLocale())])->all();
    }

    public function label(string $key, ?string $locale = null): string
    {
        $type = VendorDocumentType::query()->where('key', $key)->first();

        return $type?->getTranslation('name', $locale ?? app()->getLocale()) ?? $key;
    }
}
