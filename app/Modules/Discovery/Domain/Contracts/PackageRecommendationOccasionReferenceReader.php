<?php

declare(strict_types=1);

namespace App\Modules\Discovery\Domain\Contracts;

interface PackageRecommendationOccasionReferenceReader
{
    public function hasReferencesToOccasion(int $occasionId): bool;
}
