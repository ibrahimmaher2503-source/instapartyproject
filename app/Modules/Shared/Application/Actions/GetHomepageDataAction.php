<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Discovery\Domain\Contracts\PackageRecommendationReader;
use App\Modules\Shared\Domain\Models\HomepageSettings;

class GetHomepageDataAction
{
    public function __construct(
        private readonly GetHomepageBlocksAction $blocks,
        private readonly PackageRecommendationReader $packages,
    ) {}

    /** @return array{hero_image_url: ?string, blocks: array<int, array<string, mixed>>, packages: array<int, array<string, mixed>>} */
    public function execute(string $locale): array
    {
        $settings = HomepageSettings::current();

        return [
            'hero_image_url' => $settings->getFirstMediaUrl('hero') ?: null,
            'blocks' => $this->blocks->execute($locale),
            'packages' => $this->packages->publishedForHomepage($locale),
        ];
    }
}
