<?php

declare(strict_types=1);

namespace App\Modules\Shared\Application\Actions;

use App\Modules\Discovery\Domain\Contracts\PackageRecommendationReader;
use App\Modules\Shared\Domain\Enums\HomeBlockType;
use App\Modules\Shared\Domain\Models\HomeBlock;
use App\Modules\Shared\Domain\Models\HomepageSettings;

class GetHomepageDataAction
{
    public function __construct(
        private readonly GetHomepageBlocksAction $blocks,
        private readonly PackageRecommendationReader $packages,
    ) {}

    /** @return array{hero_image_url: ?string, blocks: array<int, array<string, mixed>>, vendor_join_configured: bool, packages: array<int, array<string, mixed>>} */
    public function execute(string $locale): array
    {
        $settings = HomepageSettings::current();

        return [
            'hero_image_url' => $settings->getFirstMediaUrl('hero') ?: null,
            'blocks' => $this->blocks->execute($locale),
            'vendor_join_configured' => HomeBlock::query()->where('block_type', HomeBlockType::VendorJoin->value)->exists(),
            'packages' => $this->packages->publishedForHomepage($locale),
        ];
    }
}
