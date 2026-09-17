<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource\Pages;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorRentalServiceResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListVendorRentalServices extends ListRecords
{
    protected static string $resource = VendorRentalServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => (bool) auth()->user()?->vendorProfile?->approvedTypes()
                    ->where('product_type', ProductType::Rental->value)
                    ->exists())
                ->before(function (): void {
                    $approved = (bool) auth()->user()?->vendorProfile?->approvedTypes()
                        ->where('product_type', ProductType::Rental->value)
                        ->exists();
                    if (! $approved) {
                        Notification::make()
                            ->title(__('vendor-portal.services.not_approved_for_type'))
                            ->warning()
                            ->send();
                    }
                }),
        ];
    }
}
