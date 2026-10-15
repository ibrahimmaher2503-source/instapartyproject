<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\User;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Shared\Application\Services\OperationalAnalyticsService;
use App\Modules\Shared\Filament\Pages\OperationalAnalyticsPage;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('compares non-overlapping real booking cohorts with honest zero denominators', function (): void {
    $this->freezeTime();
    $submitted = Booking::factory()->create(['created_at' => now()->subDays(2), 'submitted_at' => now()->subDay()]);
    Booking::factory()->create(['created_at' => now()->subDays(3), 'submitted_at' => null, 'lifecycle_status' => 'draft']);
    Booking::factory()->create(['created_at' => now()->subDays(10), 'submitted_at' => null, 'lifecycle_status' => 'draft']);
    Booking::factory()->create(['created_at' => now()->subDays(20)]);
    Payment::factory()->captured()->create(['booking_id' => $submitted->id, 'captured_at' => now()->subDay()]);
    BookingVendor::factory()->create(['booking_id' => $submitted->id]);
    $periods = app(OperationalAnalyticsService::class)->summarize(7);
    expect($periods['current']['drafts'])->toBe(2)->and($periods['previous']['drafts'])->toBe(1)
        ->and($periods['current']['submitted'])->toBe(1)->and($periods['current']['paid'])->toBe(1)
        ->and($periods['current']['conversion'])->toBe(50)->and($periods['current']['payment_conversion'])->toBe(100)
        ->and($periods['previous']['payment_conversion'])->toBeNull();
});

it('denies operational analytics to customers through a Livewire request', function (): void {
    $this->actingAs(User::factory()->asCustomer()->create());
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Livewire::test(OperationalAnalyticsPage::class)->assertForbidden();
});
