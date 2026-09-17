<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Domain\Contracts;

interface PackageRecommendationReader
{
    /** @return array<int, array<string, mixed>> */
    public function publishedForHomepage(string $locale): array;

    /** @return array<int, array<string, mixed>> */
    public function publishedForContext(string $locale, ?string $occasionCode): array;

    /**
     * @param  list<int>  $categoryIds
     * @return array<int, array<string, mixed>>
     */
    public function publishedForCategories(string $locale, array $categoryIds): array;
}
