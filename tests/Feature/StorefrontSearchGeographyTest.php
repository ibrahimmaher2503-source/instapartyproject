<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\Models\Service;
use App\Modules\Geography\Domain\Models\City;
use App\Modules\Geography\Domain\Models\Governorate;
use App\Modules\Geography\Domain\Models\Region;
use App\Modules\Identity\Domain\Models\VendorProfile;

function searchLocation(string $name): array
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

it('requires a matching governorate whenever a city filters storefront search', function (): void {
    [$governorate] = searchLocation('Alpha');
    [, $otherCity] = searchLocation('Beta');

    $this->get('/en/search?city_public_id='.$otherCity->public_id)
        ->assertRedirect()
        ->assertSessionHasErrors('governorate_public_id');

    $this->get('/en/search?governorate_public_id='.$governorate->public_id.'&city_public_id='.$otherCity->public_id)
        ->assertRedirect()
        ->assertSessionHasErrors('city_public_id');
});

it('loads only matching cities and applies governorate and city filters', function (): void {
    config(['scout.driver' => 'database']);

    [$governorate, $city] = searchLocation('Alpha');
    [$otherGovernorate, $otherCity] = searchLocation('Beta');

    $vendor = VendorProfile::factory()->approved()->create([
        'primary_governorate_id' => $governorate->id,
        'primary_city_id' => $city->id,
    ]);
    $otherVendor = VendorProfile::factory()->approved()->create([
        'primary_governorate_id' => $otherGovernorate->id,
        'primary_city_id' => $otherCity->id,
    ]);

    Service::factory()->published()->create([
        'vendor_profile_id' => $vendor->id,
        'name' => ['en' => 'Alpha Search Service', 'ar' => 'خدمة ألفا'],
    ]);
    Service::factory()->published()->create([
        'vendor_profile_id' => $otherVendor->id,
        'name' => ['en' => 'Beta Search Service', 'ar' => 'خدمة بيتا'],
    ]);

    $this->get('/en/search?governorate_public_id='.$governorate->public_id)
        ->assertOk()
        ->assertSee('data-search-governorate', false)
        ->assertSee('data-search-city', false)
        ->assertSee('value="'.$city->public_id.'"', false)
        ->assertDontSee('value="'.$otherCity->public_id.'"', false)
        ->assertSee('Alpha Search Service')
        ->assertDontSee('Beta Search Service');

    $this->get('/en/search?governorate_public_id='.$governorate->public_id.'&city_public_id='.$city->public_id)
        ->assertOk()
        ->assertSee('Alpha Search Service')
        ->assertDontSee('Beta Search Service');
});
