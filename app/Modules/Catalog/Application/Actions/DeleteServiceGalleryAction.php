<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Shared\Application\Actions\DeleteMediaAction;

/**
 * Service.gallery hard-delete — wraps shared DeleteMediaAction.
 */
final class DeleteServiceGalleryAction
{
    public function __construct(private readonly DeleteMediaAction $delete) {}

    /**
     * @return array{deleted_public_id: string, remaining_count: int, cdn_cache_busted: bool, display_order_version: int}
     */
    public function execute(Service $service, string $mediaPublicId): array
    {
        return $this->delete->execute($service, 'gallery', $mediaPublicId);
    }
}
