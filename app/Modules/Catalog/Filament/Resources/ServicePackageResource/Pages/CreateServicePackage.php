<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Resources\ServicePackageResource\Pages;

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Filament\Resources\ServicePackageResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateServicePackage extends CreateRecord
{
    protected static string $resource = ServicePackageResource::class;

    protected ?bool $hasDatabaseTransactions = true;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $service = Service::query()->with('vendor.approvedTypes')->find($data['service_id'] ?? null);
        if ($service === null || ! ServicePackageResource::serviceIsEligible($service)) {
            throw ValidationException::withMessages(['service_id' => __('catalog.package_service_ineligible')]);
        }

        $data['currency'] = $service->base_price_currency;

        return $data;
    }
}
