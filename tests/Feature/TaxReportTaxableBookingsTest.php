<?php

declare(strict_types=1);

use App\Modules\Booking\Domain\Models\Booking;
use App\Modules\Tax\Filament\Pages\TaxReportPage;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('includes fully and partially paid bookings in the VAT report', function (): void {
    $from = now()->startOfMonth();
    $to = now()->endOfMonth();

    Booking::factory()->create([
        'payment_status' => 'paid',
        'total_vat_minor' => 140_000,
        'created_at' => now()->startOfMonth()->addDay(),
    ]);
    Booking::factory()->create([
        'payment_status' => 'partial',
        'total_vat_minor' => 70_000,
        'created_at' => now()->startOfMonth()->addDays(2),
    ]);
    Booking::factory()->create([
        'payment_status' => 'unpaid',
        'total_vat_minor' => 50_000,
        'created_at' => now()->startOfMonth()->addDays(3),
    ]);
    Booking::factory()->create([
        'payment_status' => 'paid',
        'total_vat_minor' => 999_000,
        'created_at' => now()->subMonths(2),
    ]);

    $page = new TaxReportPage;
    $page->data = [
        'date_from' => $from->toDateString(),
        'date_to' => $to->toDateString(),
    ];

    expect($page->getStats())->toMatchArray([
        'total_bookings' => 2,
        'total_vat' => 210_000,
    ]);
});
