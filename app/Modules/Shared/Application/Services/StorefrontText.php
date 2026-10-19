<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

use Illuminate\Support\Collection;

final class StorefrontText
{
    /** @return Collection<int, array{public_id: string, name: string}> */
    public function vendorGovernorates(mixed $vendor, ?string $locale = null): Collection
    {
        if ($vendor === null) {
            return collect();
        }

        return collect([$vendor->primaryGovernorate ?? $vendor->primaryCity?->governorate])
            ->merge($vendor->coverageAreas->map(fn ($area) => $area->city?->governorate))
            ->filter()
            ->unique('public_id')
            ->map(fn ($governorate): array => [
                'public_id' => (string) $governorate->public_id,
                'name' => $this->translation($governorate, 'name', $locale),
            ])
            ->filter(fn (array $governorate): bool => $governorate['name'] !== '')
            ->values();
    }

    public function translation(mixed $model, string $field, ?string $locale = null): string
    {
        if (! is_object($model) || ! method_exists($model, 'getTranslation')) {
            return '';
        }

        $locale ??= app()->getLocale();
        $value = (string) $model->getTranslation($field, $locale, useFallbackLocale: false);

        if ($this->isUnavailable($value) && $locale !== 'en') {
            $value = (string) $model->getTranslation($field, 'en', useFallbackLocale: false);
        }

        return $this->isUnavailable($value) ? '' : $value;
    }

    private function isUnavailable(string $value): bool
    {
        return trim($value) === '' || preg_match('/[\p{L}\p{N}]/u', $value) !== 1;
    }
}
