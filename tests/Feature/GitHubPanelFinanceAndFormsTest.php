<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Enums\VendorSubStatus;
use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Booking\Domain\Models\BookingVendor;
use App\Modules\Identity\Domain\Models\VendorProfile;
use App\Modules\Payments\Domain\Models\Payment;
use App\Modules\Shared\Application\Services\VendorDashboardSummaryService;
use App\Modules\Shared\Filament\Widgets\AdminDashboardSectionsWidget;
use Brick\Money\Money;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;

it('uses a date picker and identifies the exact record before deletion', function (): void {
    $date = DatePicker::make('effective_from');
    expect($date->isNative())->toBeFalse()->and($date->getDisplayFormat())->toBe('Y-m-d');

    $booking = Booking::factory()->create();
    $delete = DeleteAction::make()->record($booking);
    expect($delete->getModalHeading())->toContain($booking->public_id);
});

it('keeps dashboard EGP totals and current and previous chart windows separate', function (): void {
    $this->travelTo(now('UTC')->setDate(2026, 10, 13)->setTime(12, 0));
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Payment::factory()->captured()->create(['amount_minor' => 1000, 'created_at' => now()]);
    Payment::factory()->captured()->create(['amount_minor' => 500, 'created_at' => now()->subDays(10)]);
    Payment::factory()->captured()->create(['amount_minor' => 700, 'created_at' => now()->startOfDay()->subDays(6)->addHour()]);
    Payment::factory()->captured()->create(['amount_minor' => 999999, 'amount_currency' => 'USD', 'created_at' => now()]);

    $data = (new ReflectionMethod(AdminDashboardSectionsWidget::class, 'financialData'))->invoke(
        new AdminDashboardSectionsWidget,
        ['payments' => true, 'refunds' => false, 'commissions' => false, 'withdrawals' => false],
    );

    expect($data['kpis'][0]['value'])->toBe(Money::ofMinor(2200, 'EGP')->formatTo(app()->getLocale()))
        ->and(array_sum(array_column($data['chart']['series'][0]['values'], 'value')))->toBe(1700)
        ->and($data['comparison'])->toBe(__('shared::panel.comparison', [
            'current' => Money::ofMinor(1700, 'EGP')->formatTo(app()->getLocale()),
            'previous' => Money::ofMinor(500, 'EGP')->formatTo(app()->getLocale()),
        ]));
});

it('hides captured payment comparisons when only refunds are accessible', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Payment::factory()->captured()->create(['amount_minor' => 999999]);

    $data = (new ReflectionMethod(AdminDashboardSectionsWidget::class, 'financialData'))->invoke(
        new AdminDashboardSectionsWidget,
        ['payments' => false, 'refunds' => true, 'commissions' => false, 'withdrawals' => false],
    );

    expect($data['comparison'])->toBeNull()
        ->and($data['kpis'])->toBe([])
        ->and($data['statuses'])->toBe([])
        ->and($data['chart']['series'])->toHaveCount(1)
        ->and($data['chart']['series'][0]['label'])->toBe(__('shared::dashboard.financials.refunds'));
});

it('counts actionable foreign-currency payment queues without mixing their amounts', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('admin'));
    Payment::factory()->failed()->create(['amount_currency' => 'USD']);
    Payment::factory()->create(['amount_currency' => 'USD']);
    $data = (new ReflectionMethod(AdminDashboardSectionsWidget::class, 'financialData'))->invoke(
        new AdminDashboardSectionsWidget,
        ['payments' => true, 'refunds' => false, 'commissions' => false, 'withdrawals' => false],
    );
    expect($data['attention'][0]['value'])->toBe(1)
        ->and($data['statuses'][1]['value'])->toBe(1)
        ->and($data['statuses'][3]['value'])->toBe(1)
        ->and($data['kpis'][1]['value'])->toBe(Money::ofMinor(0, 'EGP')->formatTo(app()->getLocale()));
});

it('does not relabel foreign vendor revenue as EGP', function (): void {
    $vendor = VendorProfile::factory()->approved()->create();
    BookingVendor::factory()->create([
        'vendor_profile_id' => $vendor->id, 'sub_status' => VendorSubStatus::Accepted,
        'vendor_payout_minor' => 1000, 'vendor_payout_currency' => 'EGP',
    ]);
    BookingVendor::factory()->create([
        'vendor_profile_id' => $vendor->id, 'sub_status' => VendorSubStatus::Accepted,
        'vendor_payout_minor' => 999999, 'vendor_payout_currency' => 'USD',
    ]);

    $summary = app(VendorDashboardSummaryService::class)->summarize($vendor);
    expect($summary['month_to_date']['revenue_minor'])->toBe(1000)
        ->and($summary['month_to_date']['currency'])->toBe('EGP')
        ->and($summary['month_to_date']['bookings'])->toBe(2);
});
