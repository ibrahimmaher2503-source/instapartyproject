<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Services;

final class StorefrontImageFallback
{
    /**
     * @param  array<int, string|null>  $context
     */
    public function service(string $publicId, string $productType, array $context = []): string
    {
        $searchable = mb_strtolower(implode(' ', array_filter($context)));

        foreach (config('storefront.category_fallbacks', []) as $needle => $path) {
            if (str_contains($searchable, mb_strtolower((string) $needle))) {
                return (string) $path;
            }
        }

        $typeFallback = config("storefront.product_type_fallbacks.{$productType}");

        if (is_string($typeFallback) && $typeFallback !== '') {
            return $typeFallback;
        }

        $fallbacks = config('storefront.service_fallbacks', []);

        if ($fallbacks === []) {
            return 'images/home-hero/cake.png';
        }

        return (string) $fallbacks[abs(crc32($publicId)) % count($fallbacks)];
    }
}
