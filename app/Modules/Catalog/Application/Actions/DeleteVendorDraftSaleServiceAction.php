<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\States\ServiceStatus\DraftState;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final class DeleteVendorDraftSaleServiceAction
{
    public function execute(Service $service, Authenticatable $actor): void
    {
        DB::transaction(function () use ($service, $actor): void {
            $locked = Service::query()->lockForUpdate()->findOrFail($service->getKey());

            if ($locked->product_type !== ProductType::Sale || ! ($locked->status instanceof DraftState)) {
                throw ValidationException::withMessages(['status' => __('catalog.moderation_invalid_transition')]);
            }

            Gate::forUser($actor)->authorize('delete', $locked);

            $locked->delete();
        }, 3);
    }
}
