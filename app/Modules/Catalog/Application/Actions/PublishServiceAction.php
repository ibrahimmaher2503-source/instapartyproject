<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Exceptions\ServiceCannotPublishException;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Policies\ServicePolicy;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\ApproveServiceTransition;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Shared\Application\DTOs\MediaCollectionConfig;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class PublishServiceAction
{
    /**
     * @throws AuthorizationException
     * @throws ServiceCannotPublishException when gallery min-count gate fails (FR-EXT-MED-009)
     */
    public function execute(Service $service, User $admin): Service
    {
        return DB::transaction(function () use ($service, $admin): Service {
            $lockedService = Service::query()
                ->whereKey($service->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! app(ServicePolicy::class)->approve($admin, $lockedService)) {
                throw new AuthorizationException(__('catalog.moderation_not_allowed'));
            }

            $this->assertGalleryReadyForPublish($lockedService);

            return $lockedService->status->transition(new ApproveServiceTransition($lockedService, $admin->id));
        });
    }

    /**
     * Gallery min-count gate per spec 048-media-collections-phase1 FR-EXT-MED-009
     * and `Service::$mediaCollectionRegistry['gallery']['min_files_on_publish']`.
     *
     * The rule is currently identical across rental/sale/digital (min = 1). If
     * per-type rules diverge later (e.g., digital wants 2 preview images), use
     * `match($service->product_type)` here per Constitution II.
     */
    private function assertGalleryReadyForPublish(Service $service): void
    {
        $config = MediaCollectionConfig::for(Service::class, 'gallery');

        if ($config->minFilesOnPublish <= 0) {
            return;
        }

        if ($service->getMedia('gallery')->count() < $config->minFilesOnPublish) {
            throw ServiceCannotPublishException::missingGalleryImage($service);
        }
    }
}
