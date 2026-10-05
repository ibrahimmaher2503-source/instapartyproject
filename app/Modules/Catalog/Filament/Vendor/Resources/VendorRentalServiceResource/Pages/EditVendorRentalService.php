<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource\Pages;

use App\Modules\Catalog\Application\Actions\UpdateRentalServiceAction;
use App\Modules\Catalog\Domain\Enums\ServiceStatus;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Filament\Vendor\Pages\VendorServiceAvailabilityPage;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditVendorRentalService extends EditRecord
{
    protected static string $resource = VendorRentalServiceResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Service $service */
        $service = $this->record->load('rentalDetail');

        return [
            'name' => [
                'en' => $service->getTranslation('name', 'en'),
                'ar' => $service->getTranslation('name', 'ar'),
            ],
            'short_description' => [
                'en' => $service->getTranslation('short_description', 'en') ?? '',
                'ar' => $service->getTranslation('short_description', 'ar') ?? '',
            ],
            'long_description' => [
                'en' => $service->getTranslation('long_description', 'en') ?? '',
                'ar' => $service->getTranslation('long_description', 'ar') ?? '',
            ],
            'main_category_id' => $service->category?->parent_id ?? $service->category?->id,
            'category_id' => $service->category?->parent_id !== null ? $service->category_id : null,
            'base_price_minor' => $service->base_price_minor,
            'category_field_values' => $service->category_field_values ?? [],
            'rentalDetail' => [
                'requires_electricity' => $service->rentalDetail?->requires_electricity ?? false,
                'requires_outdoor_space' => $service->rentalDetail?->requires_outdoor_space ?? false,
                'default_rental_duration_hours' => $service->rentalDetail?->default_rental_duration_hours ?? 1,
                'setup_time_minutes' => $service->rentalDetail?->setup_time_minutes ?? 0,
                'length_m' => $service->rentalDetail?->length_m,
                'width_m' => $service->rentalDetail?->width_m,
                'height_m' => $service->rentalDetail?->height_m,
            ],
        ];
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $vendorProfile = auth()->user()->vendorProfile;

        $payload = [
            'category_id' => (int) $data['category_id'],
            'name' => $data['name'],
            'short_description' => $data['short_description'] ?? ['en' => '', 'ar' => ''],
            'long_description' => $data['long_description'] ?? ['en' => '', 'ar' => ''],
            'base_price_minor' => (int) $data['base_price_minor'],
            'category_field_values' => $data['category_field_values'] ?? [],
            'requires_electricity' => (bool) ($data['rentalDetail']['requires_electricity'] ?? false),
            'requires_outdoor_space' => (bool) ($data['rentalDetail']['requires_outdoor_space'] ?? false),
            'default_rental_duration_hours' => (int) ($data['rentalDetail']['default_rental_duration_hours'] ?? 1),
            'setup_time_minutes' => isset($data['rentalDetail']['setup_time_minutes']) ? (int) $data['rentalDetail']['setup_time_minutes'] : null,
            'length_m' => isset($data['rentalDetail']['length_m']) ? (string) $data['rentalDetail']['length_m'] : null,
            'width_m' => isset($data['rentalDetail']['width_m']) ? (string) $data['rentalDetail']['width_m'] : null,
            'height_m' => isset($data['rentalDetail']['height_m']) ? (string) $data['rentalDetail']['height_m'] : null,
        ];

        /** @var Service $record */
        return app(UpdateRentalServiceAction::class)->execute($record, $vendorProfile, $payload);
    }

    protected function getSavedNotification(): ?Notification
    {
        $service = $this->record->fresh();

        if ($service->status === ServiceStatus::PendingReview) {
            return Notification::make()
                ->title(__('catalog.vendor_portal.cannot_edit_published'))
                ->warning();
        }

        return Notification::make()
            ->title(__('vendor-portal.services.saved'))
            ->success();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageAvailability')
                ->label(__('catalog.vendor_portal.block_dates'))
                ->icon('heroicon-o-calendar-days')
                ->url(fn () => VendorServiceAvailabilityPage::getUrl(['service' => $this->record->public_id], panel: 'vendor')),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
