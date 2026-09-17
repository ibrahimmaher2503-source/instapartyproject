<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Infrastructure\Repositories;

use App\Modules\Discovery\Domain\Contracts\PackageRecommendationReader;
use App\Modules\Discovery\Domain\Models\PackageRecommendation;
use Brick\Money\Money;
use Illuminate\Database\Eloquent\Builder;

final class EloquentPackageRecommendationReader implements PackageRecommendationReader
{
    public function publishedForHomepage(string $locale): array
    {
        return $this->map(
            PackageRecommendation::query()
                ->published()
                ->leftJoin('occasions', 'occasions.id', '=', 'package_recommendations.occasion_id')
                ->select('package_recommendations.*', 'occasions.public_id as occasion_public_id')
                ->orderBy('display_order')
                ->limit(6),
            $locale,
        );
    }

    public function publishedForContext(string $locale, ?string $occasionCode): array
    {
        return $this->map(
            PackageRecommendation::query()
                ->published()
                ->leftJoin('occasions', 'occasions.id', '=', 'package_recommendations.occasion_id')
                ->when($occasionCode !== null, static fn ($query) => $query->where('occasions.code', $occasionCode))
                ->select('package_recommendations.*', 'occasions.public_id as occasion_public_id')
                ->orderBy('display_order')
                ->limit(6),
            $locale,
        );
    }

    public function publishedForCategories(string $locale, array $categoryIds): array
    {
        if ($categoryIds === []) {
            return [];
        }

        return $this->map(
            PackageRecommendation::query()
                ->published()
                ->join('occasions', 'occasions.id', '=', 'package_recommendations.occasion_id')
                ->join('occasion_category', 'occasion_category.occasion_id', '=', 'occasions.id')
                ->whereIn('occasion_category.category_id', $categoryIds)
                ->select(
                    'package_recommendations.*',
                    'occasions.public_id as occasion_public_id',
                    'occasions.code as occasion_code',
                )
                ->distinct()
                ->orderBy('package_recommendations.display_order')
                ->limit(4),
            $locale,
        );
    }

    /**
     * @param  Builder<PackageRecommendation>  $query
     * @return array<int, array<string, mixed>>
     */
    private function map(Builder $query, string $locale): array
    {
        return $query->get()->map(function (PackageRecommendation $package) use ($locale): array {
            $currency = $package->budget_currency ?: 'EGP';
            $min = $package->min_budget_minor === null
                ? null
                : Money::ofMinor($package->min_budget_minor, $currency)->formatTo($locale);
            $max = $package->max_budget_minor === null
                ? null
                : Money::ofMinor($package->max_budget_minor, $currency)->formatTo($locale);

            return [
                'public_id' => $package->public_id,
                'slug' => $package->slug,
                'name' => $package->getTranslation('name', $locale, useFallbackLocale: true),
                'description' => $package->getTranslation('description', $locale, useFallbackLocale: true),
                'occasion_public_id' => $package->getAttribute('occasion_public_id'),
                'occasion_code' => $package->getAttribute('occasion_code'),
                'min_budget' => $min,
                'max_budget' => $max,
                'hero_url' => $package->getFirstMediaUrl('hero', 'large')
                    ?: $package->getFirstMediaUrl('hero')
                    ?: null,
            ];
        })
            ->all();
    }
}
