<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Enums\DayOfWeek;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Identity\Filament\Vendor\Pages\VendorBusinessHoursPage;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('explicitly explains overnight closing while preserving the entered next-day interval', function (string $locale, string $hint): void {
    app()->setLocale($locale);
    $vendor = VendorProfile::factory()->approved()->create();
    $this->actingAs($vendor->user, 'vendor');
    Filament::setCurrentPanel(Filament::getPanel('vendor'));
    $day = DayOfWeek::Thursday->value;
    Livewire::test(VendorBusinessHoursPage::class)
        ->fillForm(['day_'.$day.'_open' => true, 'day_'.$day.'_opens_at' => '23:00', 'day_'.$day.'_closes_at' => '06:00'])
        ->assertSee($hint)
        ->call('save')->assertHasNoFormErrors()->assertNotified();
    $row = $vendor->businessHours()->where('day_of_week', $day)->firstOrFail();
    expect(substr($row->opens_at, 0, 5))->toBe('23:00')->and(substr($row->closes_at, 0, 5))->toBe('06:00');
})->with([
    ['en', 'A closing time earlier than opening means the following day.'],
    ['ar', 'إذا كان وقت الإغلاق أسبق من وقت الفتح، فالإغلاق يكون في اليوم التالي.'],
]);
