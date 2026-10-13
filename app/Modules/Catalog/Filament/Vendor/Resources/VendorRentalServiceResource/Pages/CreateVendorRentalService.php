<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource\Pages;

use App\Modules\Catalog\Application\Actions\CreateRentalServiceAction;
use App\Modules\Catalog\Application\DTOs\CreateRentalServiceDTO;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateVendorRentalService extends CreateRecord
{
    protected static string $resource = VendorRentalServiceResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $vendorProfile = auth()->user()->vendorProfile;

        $dto = new CreateRentalServiceDTO(
            vendorProfileId: $vendorProfile->id,
            categoryId: (int) $data['category_id'],
            name: $data['name'],
            shortDescription: $data['short_description'] ?? ['en' => '', 'ar' => ''],
            longDescription: $data['long_description'] ?? null,
            basePriceMinor: (int) $data['base_price_minor'],
            categoryFieldValues: $data['category_field_values'] ?? [],
            requiresElectricity: (bool) ($data['rentalDetail']['requires_electricity'] ?? false),
            requiresOutdoorSpace: (bool) ($data['rentalDetail']['requires_outdoor_space'] ?? false),
            defaultRentalDurationHours: (int) ($data['rentalDetail']['default_rental_duration_hours'] ?? 1),
            setupTimeMinutes: isset($data['rentalDetail']['setup_time_minutes']) ? (int) $data['rentalDetail']['setup_time_minutes'] : null,
            teardownTimeMinutes: null,
            securityDepositMinor: null,
            minimumSpaceSqm: null,
            lengthM: isset($data['rentalDetail']['length_m']) ? (string) $data['rentalDetail']['length_m'] : null,
            widthM: isset($data['rentalDetail']['width_m']) ? (string) $data['rentalDetail']['width_m'] : null,
            heightM: isset($data['rentalDetail']['height_m']) ? (string) $data['rentalDetail']['height_m'] : null,
        );

        return app(CreateRentalServiceAction::class)->execute($dto);
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->title(__('vendor-portal.services.created'))
            ->success();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
