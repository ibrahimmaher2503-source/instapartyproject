<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorApprovedProductType;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Shared\Filament\Vendor\Widgets\VendorRecentBookingsWidget;
use App\Modules\Shared\Filament\Vendor\Widgets\VendorStatsOverviewWidget;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('renders translated service type cards and booking status labels without raw keys', function (string $locale): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $user = User::factory()->phoneVerified()->asVendor()->create();
    $profile = VendorProfile::factory()->approved()->create(['user_id' => $user->id]);
    foreach (ProductType::cases() as $type) {
        VendorApprovedProductType::factory()
            ->for($profile, 'vendorProfile')
            ->forType($type)
            ->create();
    }
    $allocation = BookingVendor::factory()->create(['vendor_profile_id' => $profile->id, 'sub_status' => VendorSubStatus::TimedOut]);
    $this->actingAs($user, 'vendor');
    $stats = Livewire::withoutLazyLoading()->test(VendorStatsOverviewWidget::class);
    foreach (ProductType::cases() as $type) {
        $key = 'vendor-portal.dashboard.services_by_type.'.$type->value;
        expect(__($key))->not->toBe($key)->not->toBe('');
        $stats->assertSee(__($key))->assertDontSee($key);
    }
    $stats->assertDontSee('vendor-portal.dashboard.')->assertDontSee('App\\Modules\\');
    config(['catalog.digital_services_enabled' => false]);
    Livewire::withoutLazyLoading()->test(VendorStatsOverviewWidget::class)
        ->assertDontSee(__('vendor-portal.dashboard.services_by_type.digital'))
        ->assertSee(__('vendor-portal.dashboard.services_by_type.rental'))
        ->assertSee(__('vendor-portal.dashboard.services_by_type.sale'));
    config(['catalog.digital_services_enabled' => true]);
    Livewire::withoutLazyLoading()->test(VendorStatsOverviewWidget::class)
        ->assertSee(__('vendor-portal.dashboard.services_by_type.digital'));
    $statusKey = 'booking.vendor_status.timed_out';
    expect(__($statusKey))->not->toBe($statusKey)->not->toBe('');
    $recent = Livewire::test(VendorRecentBookingsWidget::class)->assertCanSeeTableRecords([$allocation])
        ->assertSee(__($statusKey))->assertDontSee('timed_out')->assertDontSee($statusKey);
    if ($locale === 'ar') {
        $recent->assertDontSee('Timed Out');
    }
    foreach (VendorSubStatus::cases() as $status) {
        $key = 'booking.vendor_status.'.$status->value;
        expect(__($key))->not->toBe($key)->not->toBe('');
    }
})->with(['en', 'ar']);
