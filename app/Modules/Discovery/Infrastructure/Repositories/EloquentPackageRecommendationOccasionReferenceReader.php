<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Infrastructure\Repositories;

use App\Modules\Discovery\Domain\Contracts\PackageRecommendationOccasionReferenceReader;
use App\Modules\Discovery\Domain\Models\PackageRecommendation;

class EloquentPackageRecommendationOccasionReferenceReader implements PackageRecommendationOccasionReferenceReader
{
    public function hasReferencesToOccasion(int $occasionId): bool
    {
        return PackageRecommendation::query()->where('occasion_id', $occasionId)->exists();
    }
}
