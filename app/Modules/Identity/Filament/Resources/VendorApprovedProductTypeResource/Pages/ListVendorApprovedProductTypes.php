<?php

declare(strict_types=1);

namespace App\Modules\Identity\Filament\Resources\VendorApprovedProductTypeResource\Pages;

use App\Modules\Identity\Filament\Resources\VendorApprovedProductTypeResource;
use App\Modules\Identity\Filament\Resources\VendorProfileResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListVendorApprovedProductTypes extends ListRecords
{
    protected static string $resource = VendorApprovedProductTypeResource::class;

    public function getSubheading(): ?string
    {
        return auth()->user()?->can('approve_vendor_for_type') && VendorProfileResource::canViewAny()
            ? __('identity.product_type_approval_guidance')
            : null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manageProductTypeApprovals')
                ->label(__('identity.actions.manage_product_type_approvals'))
                ->icon('heroicon-o-arrow-top-right-on-square')
                ->url(VendorProfileResource::getUrl('index', panel: 'admin'))
                ->visible(fn (): bool => auth()->user()?->can('approve_vendor_for_type') === true
                    && VendorProfileResource::canViewAny()),
        ];
    }
}
