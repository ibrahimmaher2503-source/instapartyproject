<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Listeners;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Reviews\Domain\Events\ServiceRatingRecomputed;
use Illuminate\Contracts\Queue\ShouldQueue;

final class ReindexServiceForRatingRecomputed implements ShouldQueue
{
    public bool $afterCommit = true;

    public function handle(ServiceRatingRecomputed $event): void
    {
        $service = Service::query()->find($event->serviceId);

        if ($service === null || ! $service->shouldBeSearchable()) {
            return;
        }

        $service->searchable();
    }
}
