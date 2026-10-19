<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Vendor\Pages\VendorBookingsPage;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('restores the selected vendor booking status from the URL', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    $completed = BookingVendor::factory()->completed()->create(['vendor_profile_id' => $vendor->id]);
    $pending = BookingVendor::factory()->pending()->create(['vendor_profile_id' => $vendor->id]);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $this->actingAs($vendor->user, 'vendor');

    Livewire::withQueryParams(['statusFilter' => 'completed'])
        ->test(VendorBookingsPage::class)
        ->assertSet('statusFilter', 'completed')
        ->assertCanSeeTableRecords([$completed])
        ->assertCanNotSeeTableRecords([$pending]);
});
