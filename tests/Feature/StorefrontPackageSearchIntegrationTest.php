<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Enums\ProductType;
use App\Modules\Catalog\Domain\Models\Category;
use App\Modules\Catalog\Domain\Models\Occasion;
use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Catalog\Domain\Models\ServiceAvailabilityBlock;
use App\Modules\Discovery\Domain\Models\PackageRecommendation;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Domain\Models\Region;
use App\Modules\Identity\Domain\Models\VendorProfile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

function packageSearchLocation(string $name): array
{
    $governorate = Governorate::factory()->create([
        'name' => ['en' => $name.' Governorate', 'ar' => 'محافظة '.$name],
    ]);
    $region = Region::factory()->create(['governorate_id' => $governorate->id]);
    $city = City::factory()->create([
        'governorate_id' => $governorate->id,
        'region_id' => $region->id,
        'name' => ['en' => $name.' City', 'ar' => 'مدينة '.$name],
    ]);

    return [$governorate, $city];
}

it('filters package results through service occasion geography and exact availability', function (): void {
    config(['scout.driver' => 'database']);

    [$governorate, $city] = packageSearchLocation('Available');
    [$otherGovernorate, $otherCity] = packageSearchLocation('Unavailable');
    $occasion = Occasion::factory()->active()->create(['code' => 'integrated-package-occasion']);
    $category = Category::factory()->active()->create(['code' => 'integrated-package-category']);
    $category->occasions()->attach($occasion->id, ['sort_order' => 1]);
    $vendor = VendorProfile::factory()->approved()->create([
        'primary_governorate_id' => $governorate->id,
        'primary_city_id' => $city->id,
    ]);
    $service = Service::factory()->published()->create([
        'vendor_profile_id' => $vendor->id,
        'category_id' => $category->id,
        'product_type' => ProductType::Rental,
    ]);
    PackageRecommendation::query()->create([
        'public_id' => (string) Str::ulid(),
        'slug' => 'integrated-search-package',
        'name' => ['en' => 'Integrated search package', 'ar' => 'باقة البحث المتكاملة'],
        'description' => ['en' => 'Availability-aware package', 'ar' => 'باقة تراعي الإتاحة'],
        'occasion_id' => $occasion->id,
        'budget_currency' => 'EGP',
        'is_published' => true,
        'display_order' => 1,
    ]);
    $startsAt = Carbon::now('UTC')->addDays(3)->startOfHour();

    $availableQuery = http_build_query([
        'occasion' => $occasion->code,
        'governorate_public_id' => $governorate->public_id,
        'city_public_id' => $city->public_id,
        'event_starts_at' => $startsAt->format('Y-m-d\TH:i'),
    ]);
    $this->get('/en/search?'.$availableQuery)
        ->assertOk()
        ->assertSee('data-search-package-results', false)
        ->assertSee('Integrated search package');

    $this->get('/en/search?'.http_build_query([
        'occasion' => $occasion->code,
        'governorate_public_id' => $otherGovernorate->public_id,
        'city_public_id' => $otherCity->public_id,
        'event_starts_at' => $startsAt->format('Y-m-d\TH:i'),
    ]))
        ->assertOk()
        ->assertDontSee('data-search-package-results', false);

    ServiceAvailabilityBlock::query()->create([
        'public_id' => (string) Str::ulid(),
        'service_id' => $service->id,
        'starts_at' => $startsAt->copy()->subMinutes(30),
        'ends_at' => $startsAt->copy()->addMinutes(30),
        'reason' => ['en' => 'Reserved', 'ar' => 'محجوز'],
    ]);

    $this->get('/en/search?'.$availableQuery)
        ->assertOk()
        ->assertDontSee('data-search-package-results', false);

    $this->get('/en/search?'.http_build_query([
        'occasion' => $occasion->code,
        'governorate_public_id' => $governorate->public_id,
        'city_public_id' => $city->public_id,
        'event_starts_at' => $startsAt->copy()->addHours(2)->format('Y-m-d\TH:i'),
    ]))
        ->assertOk()
        ->assertSee('data-search-package-results', false);
});
