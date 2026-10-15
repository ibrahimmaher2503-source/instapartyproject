<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\LifecycleStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Booking\Filament\Resources\BookingsMonitorResource\Pages\ListBookingsMonitor;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Filament\Facades\Filament;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

function qaUxGrantMonitorAccess(User $admin): void
{
    $admin->givePermissionTo(Permission::findOrCreate('view_any_bookings::monitor', 'web'));
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

it('combines event date, minor-unit amount and vendor filters in the booking monitor', function (): void {
    $admin = User::factory()->asAdmin()->create();
    qaUxGrantMonitorAccess($admin);
    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $vendor = VendorProfile::factory()->approved()->create([
        'business_name' => ['en' => 'North Star Events', 'ar' => 'فعاليات النجم الشمالي'],
    ]);
    $otherVendor = VendorProfile::factory()->approved()->create([
        'business_name' => ['en' => 'Cedar Events', 'ar' => 'فعاليات الأرز'],
    ]);

    $matching = Booking::factory()->create([
        'lifecycle_status' => LifecycleStatus::VendorReview->value,
        'event_starts_at' => '2026-11-15 10:00:00',
        'total_minor' => 35000,
    ]);
    BookingVendor::factory()->create(['booking_id' => $matching->id, 'vendor_profile_id' => $vendor->id]);

    $outsideDate = Booking::factory()->create([
        'lifecycle_status' => LifecycleStatus::VendorReview->value,
        'event_starts_at' => '2026-10-31 10:00:00',
        'total_minor' => 35000,
    ]);
    BookingVendor::factory()->create(['booking_id' => $outsideDate->id, 'vendor_profile_id' => $vendor->id]);

    $outsideAmount = Booking::factory()->create([
        'lifecycle_status' => LifecycleStatus::VendorReview->value,
        'event_starts_at' => '2026-11-15 10:00:00',
        'total_minor' => 65000,
    ]);
    BookingVendor::factory()->create(['booking_id' => $outsideAmount->id, 'vendor_profile_id' => $vendor->id]);

    $outsideVendor = Booking::factory()->create([
        'lifecycle_status' => LifecycleStatus::VendorReview->value,
        'event_starts_at' => '2026-11-15 10:00:00',
        'total_minor' => 35000,
    ]);
    BookingVendor::factory()->create(['booking_id' => $outsideVendor->id, 'vendor_profile_id' => $otherVendor->id]);

    $outsideCurrency = Booking::factory()->create([
        'lifecycle_status' => LifecycleStatus::VendorReview->value,
        'event_starts_at' => '2026-11-15 10:00:00',
        'total_minor' => 35000,
        'total_currency' => 'USD',
    ]);
    BookingVendor::factory()->create(['booking_id' => $outsideCurrency->id, 'vendor_profile_id' => $vendor->id]);

    Livewire::test(ListBookingsMonitor::class)
        ->assertCanSeeTableRecords([$matching, $outsideDate, $outsideAmount, $outsideVendor, $outsideCurrency])
        ->filterTable('event_date', ['from' => '2026-11-01', 'until' => '2026-11-30'])
        ->filterTable('total_range', ['min' => '300.00', 'max' => '500.00'])
        ->filterTable('vendor_id', $vendor->id)
        ->assertCanSeeTableRecords([$matching])
        ->assertCanNotSeeTableRecords([$outsideDate, $outsideAmount, $outsideVendor, $outsideCurrency]);
});

it('builds vendor filter choices from one grouped booking-vendor row per profile', function (): void {
    $admin = User::factory()->asAdmin()->create();
    qaUxGrantMonitorAccess($admin);
    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $vendor = VendorProfile::factory()->approved()->create([
        'business_name' => ['en' => 'Repeated Vendor', 'ar' => 'مورد متكرر'],
    ]);

    foreach (range(1, 12) as $index) {
        $booking = Booking::factory()->create([
            'lifecycle_status' => LifecycleStatus::VendorReview->value,
            'reference_no' => 'QA-UX-VENDOR-'.$index,
        ]);
        BookingVendor::factory()->create([
            'booking_id' => $booking->id,
            'vendor_profile_id' => $vendor->id,
        ]);
    }

    $livewire = Livewire::test(ListBookingsMonitor::class);
    $allocationQueries = [];
    DB::listen(function (QueryExecuted $query) use (&$allocationQueries): void {
        if (str_contains(strtolower($query->sql), 'booking_vendors')) {
            $allocationQueries[] = strtolower($query->sql);
        }
    });

    app()->setLocale('en');
    $options = $livewire->instance()->getTable()->getFilters()['vendor_id']->getOptions();

    expect($options)->toBe([$vendor->id => 'Repeated Vendor'])
        ->and($allocationQueries)->toHaveCount(1)
        ->and($allocationQueries[0])->toContain('min(id)')
        ->and($allocationQueries[0])->toContain('group by');
});

it('renders navigation shortcuts and saved-view storage scoped to the signed-in admin in English and Arabic', function (string $locale, string $heading): void {
    $admin = User::factory()->asAdmin()->create();
    qaUxGrantMonitorAccess($admin);
    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $response = $this->get('/admin/bookings-monitor?lang='.$locale)->assertOk();

    $response
        ->assertSee('instaparty.admin.nav.'.$admin->id.'.v1')
        ->assertSee('data-admin-nav-shortcuts', false)
        ->assertDontSee('ip-navigation-favorite')
        ->assertSee('instaparty.admin.bookings-monitor.views.'.$admin->id.'.v1')
        ->assertSee($heading)
        ->assertSee(__('booking.filters.saved_views', [], $locale));
})->with([
    'English' => ['en', 'Favorites'],
    'Arabic' => ['ar', 'المفضلة'],
]);

it('keeps the selected admin locale ahead of the browser language', function (): void {
    $admin = User::factory()->asAdmin()->create();
    qaUxGrantMonitorAccess($admin);
    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->withSession(['locale' => 'ar'])
        ->withHeader('Accept-Language', 'en-US,en;q=0.9')
        ->get('/admin/bookings-monitor')
        ->assertOk()
        ->assertSee(__('admin.nav.shortcuts.favorites', [], 'ar'))
        ->assertSee('dir="rtl"', false);
});

it('lets an explicit language query override a saved locale on the booking create page', function (): void {
    $admin = User::factory()->asAdmin()->create();
    qaUxGrantMonitorAccess($admin);
    $this->actingAs($admin, 'web');
    Filament::setCurrentPanel(Filament::getPanel('admin'));

    $this->withSession(['locale' => 'en'])
        ->get('/admin/bookings/create?lang=ar')
        ->assertOk()
        ->assertSee("document.documentElement.dir='rtl'", false)
        ->assertSee("document.documentElement.lang='ar'", false);
});
