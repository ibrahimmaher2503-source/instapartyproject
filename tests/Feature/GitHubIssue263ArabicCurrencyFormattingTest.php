<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Service;
use Carbon\Carbon;
use Illuminate\Support\Str;

it('formats all prices consistently on Arabic service page with ج.م. and no EGP in price labels', function (): void {
    $service = Service::factory()->sale()->published()->create([
        'name' => ['en' => 'Deluxe Birthday Cake', 'ar' => 'كعكة عيد ميلاد فاخرة'],
        'base_price_minor' => 90000,
        'base_price_currency' => 'EGP',
    ]);
    $service->vendor->approvedTypes()->create(['product_type' => ProductType::Sale, 'approved_at' => now()]);

    $package = $service->packages()->create([
        'public_id' => (string) Str::ulid(),
        'code' => 'pkg-deluxe',
        'name' => ['en' => 'Deluxe Package', 'ar' => 'باقة فاخرة'],
        'description' => ['en' => 'Includes cake and candles.', 'ar' => 'تشمل الكعكة والشموع.'],
        'price_minor' => 120000,
        'currency' => 'EGP',
        'is_active' => true,
    ]);

    $package->components()->create([
        'public_id' => (string) Str::ulid(),
        'name' => ['en' => 'Candles', 'ar' => 'شموع'],
        'description' => ['en' => 'Birthday candles', 'ar' => 'شموع عيد ميلاد'],
        'quantity' => 5,
        'sort_order' => 0,
    ]);

    $startsAt = Carbon::now('Africa/Cairo')->addDays(3)->setTime(15, 0)->format('Y-m-d\\TH:i');

    $response = $this->get(route('storefront.services.show', [
        'locale' => 'ar',
        'servicePublicId' => $service->public_id,
        'event_starts_at' => $startsAt,
        'quantity' => 1,
        'timezone' => 'Africa/Cairo',
    ]));

    $response->assertOk();

    // Verify Arabic prices are rendered with ج.م.
    $content = $response->getContent();
    expect($content)->toContain('ج.م.')
        ->toContain('900.00')
        ->toContain('1,200.00');

    // Verify that the pricing card and estimate panel do not contain "EGP 900.00" or raw EGP price strings
    expect($content)->not->toContain('EGP 900.00')
        ->not->toContain('EGP 1,200.00')
        ->not->toContain('EGP 900')
        ->not->toContain('EGP 1200');
});

it('formats prices with EGP on English service page and does not contain Arabic currency symbol', function (): void {
    $service = Service::factory()->sale()->published()->create([
        'name' => ['en' => 'Deluxe Birthday Cake', 'ar' => 'كعكة عيد ميلاد فاخرة'],
        'base_price_minor' => 90000,
        'base_price_currency' => 'EGP',
    ]);
    $service->vendor->approvedTypes()->create(['product_type' => ProductType::Sale, 'approved_at' => now()]);

    $startsAt = Carbon::now('Africa/Cairo')->addDays(3)->setTime(15, 0)->format('Y-m-d\\TH:i');

    $response = $this->get(route('storefront.services.show', [
        'locale' => 'en',
        'servicePublicId' => $service->public_id,
        'event_starts_at' => $startsAt,
        'quantity' => 1,
        'timezone' => 'Africa/Cairo',
    ]));

    $response->assertOk();

    $content = $response->getContent();
    expect($content)->toContain('EGP')
        ->toContain('900.00')
        ->not->toContain('ج.م.');
});
