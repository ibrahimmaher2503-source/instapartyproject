<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource\Pages;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Filament\Vendor\Resources\VendorSaleServiceResource;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListVendorSaleServices extends ListRecords
{
    protected static string $resource = VendorSaleServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(fn (): bool => (bool) auth()->user()?->vendorProfile?->approvedTypes()
                    ->where('product_type', ProductType::Sale->value)
                    ->exists())
                ->before(function (): void {
                    $approved = (bool) auth()->user()?->vendorProfile?->approvedTypes()
                        ->where('product_type', ProductType::Sale->value)
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
