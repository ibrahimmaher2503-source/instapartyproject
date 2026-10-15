<?php

declare(strict_types=1);

namespace App\Modules\Identity\Infrastructure\Repositories;

use App\Modules\Identity\Domain\Contracts\PublicVendorPortfolioReader;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Domain\States\VendorApprovalStatus\ApprovedState;
use App\Modules\Shared\Application\Services\PublicMediaUrl;

final class EloquentPublicVendorPortfolioReader implements PublicVendorPortfolioReader
{
    private const PUBLIC_DISKS = ['public', 's3-public'];

    public function __construct(private readonly PublicMediaUrl $mediaUrls) {}

    public function forApprovedVendor(string $vendorPublicId): array
    {
        $vendor = VendorProfile::query()
            ->whereState('approval_status', ApprovedState::class)
            ->where('public_id', $vendorPublicId)
            ->first();

        if ($vendor === null) {
            return [];
        }

        $items = [];

        foreach ($vendor->getMedia('portfolio') as $media) {
            if (! in_array($media->disk, self::PUBLIC_DISKS, true)
                || ($media->conversions_disk !== null && ! in_array($media->conversions_disk, self::PUBLIC_DISKS, true))) {
                continue;
            }

            $url = $this->mediaUrls->for($media, 'large');
            $thumb = $this->mediaUrls->for($media, 'thumb');

            if ($url !== null && $thumb !== null) {
                $items[] = ['url' => $url, 'thumb' => $thumb];
            }
        }

        return $items;
    }
}
