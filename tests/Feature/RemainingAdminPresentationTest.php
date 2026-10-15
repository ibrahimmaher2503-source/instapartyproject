<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Resources\BookingResource;
use App\Modules\Booking\Filament\Resources\BookingResource\Pages\ListBookings;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

it('shows readable booking context and preserves optional columns across page reloads', function (string $locale): void {
    app()->setLocale($locale);
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    $admin = User::factory()->asAdmin()->create();
    foreach (['view_any_bookings::monitor', 'view_bookings::monitor'] as $ability) {
        $admin->givePermissionTo(Permission::findOrCreate($ability, 'web'));
    }
    $this->actingAs($admin);
    $customer = User::factory()->asCustomer()->create(['name' => 'Presentation Customer']);
    $occasion = Occasion::factory()->active()->create(['name' => ['en' => 'Presentation Occasion', 'ar' => 'مناسبة العرض']]);
    $vendor = VendorProfile::factory()->approved()->create(['business_name' => ['en' => 'Presentation Vendor', 'ar' => 'مورد العرض']]);
    $booking = Booking::factory()->create([
        'customer_id' => $customer->id,
        'occasion_id' => $occasion->id,
        'reference_no' => 'IP-PRESENTATION-001',
    ]);
    BookingVendor::factory()->create(['booking_id' => $booking->id, 'vendor_profile_id' => $vendor->id]);

    $page = Livewire::test(ListBookings::class)
        ->assertCanSeeTableRecords([$booking])
        ->assertTableColumnStateSet('customer.name', $customer->name, $booking)
        ->assertTableColumnStateSet('occasion_id', $occasion->getTranslation('name', $locale), $booking)
        ->assertTableColumnStateSet('vendors.business_names', $vendor->getTranslation('business_name', $locale), $booking)
        ->assertTableColumnVisible('event_starts_at')
        ->assertSee('IP-PRESENTATION-001')
        ->assertDontSee('booking.lifecycle_status.draft');

    expect($page->instance()->getTable()->getRecordUrl($booking))
        ->toBe(BookingResource::getUrl('view', ['record' => $booking->public_id]));
    expect($page->instance()->isTableColumnToggledHidden('public_id'))->toBeTrue();
    $page->set('toggledTableColumns.public_id', true);
    expect(Livewire::test(ListBookings::class)->instance()->isTableColumnToggledHidden('public_id'))->toBeFalse();
})->with(['en', 'ar']);
