<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Policies\ServicePolicy;
use App\Modules\Catalog\Domain\States\ServiceStatus\ArchivedState;
use App\Modules\Catalog\Domain\States\ServiceStatus\Transitions\ArchiveServiceTransition;
use App\Modules\Identity\Domain\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ArchiveServiceAction
{
    public function execute(Service $service, User $admin): Service
    {
        return DB::transaction(function () use ($service, $admin): Service {
            $lockedService = Service::query()
                ->whereKey($service->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedService->status->canTransitionTo(ArchivedState::class, $admin->id)) {
                throw ValidationException::withMessages([
                    'status' => __('catalog.moderation_invalid_transition'),
                ]);
            }

            if (! app(ServicePolicy::class)->archive($admin, $lockedService)) {
                throw new AuthorizationException(__('catalog.moderation_not_allowed'));
            }

            return $lockedService->status->transition(new ArchiveServiceTransition($lockedService, $admin->id));
        });
    }
}
