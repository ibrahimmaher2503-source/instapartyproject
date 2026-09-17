<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Shared\Application\Actions\ReorderMediaAction;

/**
 * Service.gallery reorder — wraps shared ReorderMediaAction with optimistic
 * concurrency on gallery_order_version. Per contracts/media-reorder.md.
 */
final class ReorderServiceGalleryAction
{
    public function __construct(private readonly ReorderMediaAction $reorder) {}

    /**
     * @param  array<int, string>  $orderPublicIds
     * @return array{items: array<int, array<string, mixed>>, display_order_version: int, hero_changed: bool, cdn_cache_busted: bool}
     */
    public function execute(Service $service, array $orderPublicIds, int $expectedVersion): array
    {
        return $this->reorder->execute($service, 'gallery', $orderPublicIds, $expectedVersion);
    }
}
